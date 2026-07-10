<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Controllers;

use Icinga\Application\Logger;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Map\Web\Controller\MapController;
use ipl\Stdlib\Filter as IplFilter;
use ipl\Web\Filter\QueryString;
use ipl\Sql\Expression;

final class DataController extends MapController
{
    private string $stateColumn;
    private ?IplFilter\Rule $filter = null;
    private bool $onlyProblems = false;
    private array $points = [];

    /**
     * Get JSON state objects
     */
    public function pointsAction(): never
    {
        try {
            $this->initializeParameters();
            $this->addIcingadbWebToPoints();
        } catch (\Exception $e) {
            Logger::error('Map module: %s', $e);

            $this->points = ['message' => $e->getMessage()];

            // Only expose the stack trace when debug logging is enabled
            if (Logger::getInstance()->getLevel() === Logger::DEBUG) {
                $this->points['trace'] = $e->getTraceAsString();
            }
        }

        $this->outputJson($this->points);
    }

    private function initializeParameters(): void
    {
        $config = $this->Config();
        $stateType = strtolower($this->params->shift('stateType', 
            $config->get('map', 'stateType', 'soft')
        ));

        $userPreferences = $this->Auth()->getUser()->getPreferences();
        $stateType = strtolower($userPreferences->getValue('map', 'stateType', $stateType));

        // Whitelist: only 'hard' and 'soft' are valid state types
        if (!in_array($stateType, ['hard', 'soft'], true)) {
            $stateType = 'soft';
        }

        $this->params->shift('objectType');
        $this->onlyProblems = (bool) $this->params->shift('problems', false);

        $filterString = (string) $this->params;
        if ($filterString !== '') {
            $this->filter = QueryString::parse($filterString);
        }

        $this->stateColumn = $stateType === 'hard' ? 'hard_state' : 'soft_state';
    }

    private function addIcingadbWebToPoints(): void
    {
        $db = $this->icingadbUtils->getDb();
        $col = $this->stateColumn;

        // Pre-build expressions for better readability
        $expressions = $this->buildExpressions($col);

        $hostQuery = Host::on($db)
            ->with(['state', 'service', 'service.state'])
            ->columns([
                'id',
                'name',
                'display_name',
                'vars.geolocation',
                'vars.map_icon',
                ...$expressions
            ])
            ->filter(IplFilter::like('host.vars.geolocation', '*'));

        $hostQuery->getSelectBase()->groupBy([
            'host.id',
            'host.name', 
            'host.display_name',
            'host_vars_geolocation',
            'host_vars_map_icon'
        ]);

        if ($this->filter !== null) {
            $hostQuery->filter($this->filter);
        }

        if ($this->onlyProblems) {
            $hostQuery->filter(IplFilter::equal('service.state.is_problem', 'y'));
        }

        $this->icingadbUtils->applyRestrictions($hostQuery);

        $this->processResults($hostQuery->execute());
    }

    private function buildExpressions(string $col): array
    {
        return [
            'hosts_down_handled'          => new Expression("SUM(CASE WHEN host_state.{$col} = 1 AND (host_state.is_handled = 'y' OR host_state.is_reachable = 'n') THEN 1 ELSE 0 END)"),
            'hosts_down_unhandled'        => new Expression("SUM(CASE WHEN host_state.{$col} = 1 AND host_state.is_handled = 'n' AND host_state.is_reachable = 'y' THEN 1 ELSE 0 END)"),
            'hosts_is_acknowledged'       => new Expression("SUM(CASE WHEN host_state.is_acknowledged = 'y' THEN 1 ELSE 0 END)"),
            'hosts_in_downtime'           => new Expression("SUM(CASE WHEN host_state.in_downtime = 'y' THEN 1 ELSE 0 END)"),
            'hosts_pending'               => new Expression("SUM(CASE WHEN host_state.{$col} = 99 THEN 1 ELSE 0 END)"),
            'hosts_total'                 => new Expression("COUNT(DISTINCT host.id)"),
            'hosts_up'                    => new Expression("SUM(CASE WHEN host_state.{$col} = 0 THEN 1 ELSE 0 END)"),
            'services_critical_handled'   => new Expression("SUM(CASE WHEN host_service_state.{$col} = 2 AND (host_service_state.is_handled = 'y' OR host_service_state.is_reachable = 'n') THEN 1 ELSE 0 END)"),
            'services_critical_unhandled' => new Expression("SUM(CASE WHEN host_service_state.{$col} = 2 AND host_service_state.is_handled = 'n' AND host_service_state.is_reachable = 'y' THEN 1 ELSE 0 END)"),
            'services_ok'                 => new Expression("SUM(CASE WHEN host_service_state.{$col} = 0 THEN 1 ELSE 0 END)"),
            'services_pending'            => new Expression("SUM(CASE WHEN host_service_state.{$col} = 99 THEN 1 ELSE 0 END)"),
            'services_total'              => new Expression("SUM(CASE WHEN service_id IS NOT NULL THEN 1 ELSE 0 END)"),
            'services_unknown_handled'    => new Expression("SUM(CASE WHEN host_service_state.{$col} = 3 AND (host_service_state.is_handled = 'y' OR host_service_state.is_reachable = 'n') THEN 1 ELSE 0 END)"),
            'services_unknown_unhandled'  => new Expression("SUM(CASE WHEN host_service_state.{$col} = 3 AND host_service_state.is_handled = 'n' AND host_service_state.is_reachable = 'y' THEN 1 ELSE 0 END)"),
            'services_warning_handled'    => new Expression("SUM(CASE WHEN host_service_state.{$col} = 1 AND (host_service_state.is_handled = 'y' OR host_service_state.is_reachable = 'n') THEN 1 ELSE 0 END)"),
            'services_warning_unhandled'  => new Expression("SUM(CASE WHEN host_service_state.{$col} = 1 AND host_service_state.is_handled = 'n' AND host_service_state.is_reachable = 'y' THEN 1 ELSE 0 END)"),
        ];
    }

    private function processResults(iterable $result): void
    {
        foreach ($result as $row) {
            $geolocation = $row->vars['geolocation'] ?? null;
            
            if ($geolocation === null || !preg_match($this->coordinatePattern, $geolocation)) {
                continue;
            }

            $hostname = $row->name;
            
            if (isset($this->points['hosts'][$hostname])) {
                continue;
            }

            $this->points['hosts'][$hostname] = $this->buildHostPoint($row, $geolocation);
        }
    }

    private function buildHostPoint(object $row, string $geolocation): array
    {
        $hasDownHandled = $row->hosts_down_handled > 0;
        $hasDownUnhandled = $row->hosts_down_unhandled > 0;

        return [
            'host_name'                   => $row->name,
            'host_display_name'           => $row->display_name,
            'coordinates'                 => explode(',', $geolocation),
            'icon'                        => $row->vars['map_icon'] ?? null,
            'host_state'                  => $hasDownUnhandled ? 1 : 0,
            'host_in_downtime'            => $hasDownHandled ? 1 : 0,
            'hosts_down_handled'          => (int) $hasDownHandled,
            'hosts_down_unhandled'        => (int) $hasDownUnhandled,
            'hosts_is_acknowledged'       => $row->hosts_is_acknowledged > 0 ? 1 : 0,
            'hosts_in_downtime'           => $row->hosts_in_downtime > 0 ? 1 : 0,
            'hosts_pending'               => $row->hosts_pending > 0 ? 1 : 0,
            'hosts_total'                 => $row->hosts_total > 0 ? 1 : 0,
            'hosts_up'                    => $row->hosts_up > 0 ? 1 : 0,
            'services_critical_handled'   => (int) $row->services_critical_handled,
            'services_critical_unhandled' => (int) $row->services_critical_unhandled,
            'services_ok'                 => (int) $row->services_ok,
            'services_pending'            => (int) $row->services_pending,
            'services_total'              => (int) $row->services_total,
            'services_unknown_handled'    => (int) $row->services_unknown_handled,
            'services_unknown_unhandled'  => (int) $row->services_unknown_unhandled,
            'services_warning_handled'    => (int) $row->services_warning_handled,
            'services_warning_unhandled'  => (int) $row->services_warning_unhandled,
            'host_state_service'          => $this->determineHostStateService($row),
            'services'                    => [],
        ];
    }

    private function determineHostStateService(object $row): int
    {
        return match (true) {
            $row->hosts_down_unhandled > 0,
            $row->hosts_down_handled > 0,
            $row->services_critical_unhandled > 0 => 2,
            $row->services_warning_unhandled > 0  => 1,
            $row->services_unknown_unhandled > 0  => 3,
            $row->hosts_pending > 0,
            $row->services_pending > 0            => 99,
            default                               => 0,
        };
    }

    private function outputJson(array $data): never
    {
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $response->setHeader('Cache-Control', 'no-store', true);
        $response->setBody(json_encode($data, JSON_THROW_ON_ERROR));
        $response->sendResponse();
        exit();
    }
}
