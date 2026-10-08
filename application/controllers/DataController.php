<?php

namespace Icinga\Module\Map\Controllers;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use Icinga\Module\Map\Web\Controller\MapController;
use Icinga\Module\Monitoring\DataView\DataView;
use ipl\Orm\Model;
use ipl\Stdlib\Filter as IplFilter;
use ipl\Web\Filter\QueryString;
use ipl\Sql\Expression;

class DataController extends MapController
{
    /**
     * Apply filters on a DataView
     *
     * @param DataView $dataView The DataView to apply filters on
     *
     * @return DataView $dataView
     */
    protected function filterQuery(DataView $dataView)
    {
        $this->setupFilterControl($dataView, null, null, ['stateType', 'objectType', 'problems']);
        return $dataView;
    }

    private $stateColumn;
    private $stateChangeColumn;
    private $filter;

    /** @var bool Whether to show ony problem services */
    private $onlyProblems;
    private $points = [];

    /**
     * Get JSON state objects
     */
    public function pointsAction()
    {
        try {
            // Borrowed from monitoring module
            // Handle soft and hard states
            $config = $this->config();
            $stateType = strtolower($this->params->shift('stateType',
                $config->get('map', 'stateType', 'soft')
            ));

            $userPreferences = $this->Auth()->getUser()->getPreferences();
            if ($userPreferences->has("map")) {
                $stateType = $userPreferences->getValue("map", "stateType", $stateType);
            }
            $objectType = strtolower($this->params->shift('objectType',
                $config->get('map', 'objectType', 'all')
            ));

            $this->onlyProblems = (bool)$this->params->shift('problems', false);

            if ($this->isUsingIcingadb) {
                $this->filter = QueryString::parse((string) $this->params);
            }

            if ($stateType === 'hard') {
                $this->stateColumn = 'hard_state';
                $this->stateChangeColumn = 'last_hard_state_change';
                if ($this->isUsingIcingadb) {
                    $this->stateChangeColumn = 'last_state_change';
                }
            } else {
                $this->stateColumn = 'state';
                $this->stateChangeColumn = 'last_state_change';
                if ($this->isUsingIcingadb) {
                    $this->stateColumn = 'soft_state';
                }
            }

            if ($objectType === 'icingadbweb' && ! $this->isUsingIcingadb) {
                // The summary needs IcingaDB, show everything rather than an empty map
                $objectType = 'all';
            }

            if ($objectType === 'icingadbweb') {
                $this->addIcingadbWebToPoints();
            }

            if (in_array($objectType, ['all', 'host'])) {
                if ($this->isUsingIcingadb) {
                    $this->addIcingadbHostsToPoints();
                } else {
                    $this->addHostsToPoints();
                }
            }

            if (in_array($objectType, ['all', 'service'])) {
                if ($this->isUsingIcingadb) {
                   $this->addIcingadbServicesToPoints();
                } else {
                    $this->addServicesToPoints();
                }
            }
        } catch (\Exception $e) {
            $this->points['message'] = $e->getMessage();
            $this->points['trace'] = $e->getTraceAsString();
        }

        echo json_encode($this->points);
        exit();
    }

    private function addHostsToPoints()
    {
        // get host data
        $hostQuery = $this->backend
            ->select()
            ->from('hoststatus', array(
                'host_display_name',
                'host_name',
                'host_acknowledged',
                'host_state' => 'host_' . $this->stateColumn,
                'host_last_state_change' => 'host_' . $this->stateChangeColumn,
                'host_in_downtime',
                'host_problem',
                'coordinates' => '_host_geolocation',
                'icon' => '_host_map_icon',
            ))
            ->applyFilter(Filter::fromQueryString('_host_geolocation >'));

        $this->applyRestriction('monitoring/filter/objects', $hostQuery);
        $this->filterQuery($hostQuery);

        // get service data
        $serviceQuery = $this->backend
            ->select()
            ->from('servicestatus', array(
                'host_name',
                'service_display_name',
                'service_name' => 'service',
                'service_acknowledged',
                'service_state' => 'service_' . $this->stateColumn,
                'service_last_state_change' => 'service_' . $this->stateChangeColumn,
                'service_in_downtime'
            ))
            ->applyFilter(Filter::fromQueryString('_host_geolocation >'));

        if ($this->onlyProblems) {
            $serviceQuery->applyFilter(Filter::where('service_problem', 1));
        }

        $this->applyRestriction('monitoring/filter/objects', $serviceQuery);
        $this->filterQuery($serviceQuery);

        if ($hostQuery->count() > 0) {
            foreach ($hostQuery as $row) {
                $hostname = $row->host_name;

                $host = (array)$row;
                $host['services'] = array();

                if (!preg_match($this->coordinatePattern, $host['coordinates'])) {
                    continue;
                }

                $host['coordinates'] = explode(",", $host['coordinates']);

                $this->points['hosts'][$hostname] = $host;
            }
        }

        // add services to host
        if ($serviceQuery->count() > 0) {
            foreach ($serviceQuery as $row) {
                $hostname = $row->host_name;

                $service = (array)$row;
                unset($service['host_name']);

                if (isset($this->points['hosts'][$hostname])) {
                    $this->points['hosts'][$hostname]['services'][$service['service_display_name']] = $service;
                }
            }
        }

        // remove hosts without problems and services
        if ($this->onlyProblems) {
            foreach ($this->points['hosts'] as $name => $host) {
                if (empty($host['services']) && $host['host_problem'] != '1') {
                    unset($this->points['hosts'][$name]);
                }
            }
        }
    }

    private function addServicesToPoints()
    {
        // get services with geolocation
        $geoServiceQuery = $this->backend
            ->select()
            ->from('servicestatus', array(
                'host_display_name',
                'host_name',
                'host_acknowledged',
                'host_state' => 'host_' . $this->stateColumn,
                'host_last_state_change' => 'host_' . $this->stateChangeColumn,
                'host_in_downtime',
                'service_display_name',
                'service_name' => 'service',
                'service_acknowledged',
                'service_state' => 'service_' . $this->stateColumn,
                'service_last_state_change' => 'service_' . $this->stateChangeColumn,
                'service_in_downtime',
                'coordinates' => '_service_geolocation',
                'icon' => '_service_map_icon',

            ))->applyFilter(Filter::fromQueryString('_service_geolocation >'));

        if ($this->onlyProblems) {
            $geoServiceQuery->applyFilter(Filter::where('service_problem', 1));
        }


        $this->applyRestriction('monitoring/filter/objects', $geoServiceQuery);
        $this->filterQuery($geoServiceQuery);
        // ---

        if ($geoServiceQuery->count() > 0) {
            foreach ($geoServiceQuery as $row) {
                $identifier = $row->host_name . "!" . $row->service_name;

                $ar = (array)$row;

                $host = array_filter($ar, function ($k) {
                    return (preg_match("/^host_|^coordinates/", $k));
                }, ARRAY_FILTER_USE_KEY);

                $service = array_filter($ar, function ($k) {
                    return (preg_match("/^service_/", $k));
                }, ARRAY_FILTER_USE_KEY);

                $host['services'][$service['service_display_name']] = $service;

                if (!preg_match($this->coordinatePattern, $host['coordinates'])) {
                    continue;
                }

                $host['coordinates'] = explode(",", $host['coordinates']);
                $host['icon'] = $ar['icon'];
                $this->points['services'][$identifier] = $host;
            }
        }
    }

    private function addIcingadbWebToPoints()
    {
        $db = $this->icingadbUtils->getDb();

        $hostQuery = Host::on($db)
            ->columns([
                'id',
                'name',
                'display_name',
                'vars.geolocation',
                'vars.map_icon',
                'state.hard_state',
                'state.soft_state',
                'state.last_state_change',
                'state.is_acknowledged',
                'state.in_downtime',
                'state.is_handled',
            ])
            ->filter(IplFilter::like('host.vars.geolocation', '*'))
            ->setResultSetClass(VolatileStateResults::class);

        if ($this->filter) {
            $hostQuery->filter($this->filter);
        }

        if ($this->onlyProblems) {
            $hostQuery->filter(IplFilter::any(
                IplFilter::equal('state.is_problem', 'y'),
                IplFilter::equal('service.state.is_problem', 'y')
            ));
        }

        $this->icingadbUtils->applyRestrictions($hostQuery);

        $hosts = [];
        foreach ($hostQuery as $row) {
            if (! preg_match($this->coordinatePattern, $row->vars['geolocation'])) {
                continue;
            }

            $host = $this->populateObjectColumnsToArray($row);
            $host['host_handled'] = $row->state->is_handled ? 1 : 0;
            $host['coordinates'] = explode(",", $row->vars['geolocation']);
            $host['icon'] = $row->vars['map_icon'] ?? null;
            $host['services'] = [];
            $host['service_summary'] = [
                'state_column'       => $this->stateColumn,
                'total'              => 0,
                'ok'                 => 0,
                'pending'            => 0,
                'critical_unhandled' => 0,
                'critical_handled'   => 0,
                'warning_unhandled'  => 0,
                'warning_handled'    => 0,
                'unknown_unhandled'  => 0,
                'unknown_handled'    => 0,
            ];

            $hosts[$row->id] = $host;
        }

        if (empty($hosts)) {
            return;
        }

        // Count services in SQL, fetching one row per service does not scale
        $state = 'state.' . $this->stateColumn;
        $count = function (string $condition, string ...$columns) {
            return new Expression("SUM(CASE WHEN $condition THEN 1 ELSE 0 END)", $columns);
        };

        $columns = [
            'host_id',
            'total'   => new Expression('COUNT(*)'),
            'ok'      => $count('%s = 0', $state),
            'pending' => $count('%s = 99', $state),
        ];
        foreach (['critical' => 2, 'warning' => 1, 'unknown' => 3] as $name => $value) {
            $columns["{$name}_unhandled"] = $count(
                "%s = $value AND %s = 'n' AND %s = 'y'",
                $state,
                'state.is_handled',
                'state.is_reachable'
            );
            $columns["{$name}_handled"] = $count(
                "%s = $value AND (%s = 'y' OR %s = 'n')",
                $state,
                'state.is_handled',
                'state.is_reachable'
            );
        }

        $summaryQuery = Service::on($db)
            ->columns($columns)
            ->filter(IplFilter::like('host.vars.geolocation', '*'))
            ->disableDefaultSort();

        if ($this->filter) {
            $summaryQuery->filter($this->filter);
        }

        if ($this->onlyProblems) {
            $summaryQuery->filter(IplFilter::equal('service.state.is_problem', 'y'));
        }

        $this->icingadbUtils->applyRestrictions($summaryQuery);
        $summaryQuery->getSelectBase()->groupBy('service.host_id');

        foreach ($summaryQuery as $row) {
            if (! isset($hosts[$row->host_id])) {
                continue;
            }

            foreach ($columns as $alias => $column) {
                if (is_string($alias)) {
                    $hosts[$row->host_id]['service_summary'][$alias] = (int) $row->$alias;
                }
            }
        }

        foreach ($hosts as $host) {
            $this->points['hosts'][$host['host_name']] = $host;
        }
    }

    private function addIcingadbHostsToPoints()
    {
        $hostQuery = Host::on($this->icingadbUtils->getDb())
             ->columns([
                 'id',
                'name',
                'display_name',
                'vars.geolocation',
                'vars.map_icon',
                'state.is_acknowledged',
                'state.hard_state',
                'state.soft_state',
                'state.last_state_change',
                'state.is_acknowledged',
                'state.in_downtime',
                'state.is_problem',
                'service.id',
                'service.name',
                'service.display_name',
                'service.state.is_acknowledged',
                'service.state.hard_state',
                'service.state.soft_state',
                'service.state.last_state_change',
                'service.state.is_acknowledged',
                'service.state.in_downtime',
                'service.state.is_problem',
            ])
            ->filter(IplFilter::like('host.vars.geolocation', '*'))
            ->setResultSetClass(VolatileStateResults::class);

        if ($this->filter) {
            $hostQuery->Filter($this->filter);
        }

        if ($this->onlyProblems) {
            $hostQuery->Filter(IplFilter::equal('service.state.is_problem', 'y'));
        }

        $this->icingadbUtils->applyRestrictions($hostQuery);

        $hostQuery = $hostQuery->execute();
        if (! $hostQuery->hasResult()) {
            return;
        }

        foreach ($hostQuery as $row) {
            if (! preg_match($this->coordinatePattern, $row->vars['geolocation'])) {
                continue;
            }

            $hostname = $row->name;
            if (! isset($this->points['hosts'][$hostname])) {
                $host = $this->populateObjectColumnsToArray($row);
                $host['host_problem']               = $row->state->is_problem ? 1 : 0;
                $host['coordinates']                = $row->vars['geolocation'];
                $host['icon']                       = $row->vars['map_icon'] ?? null;
                $host['coordinates'] = explode(",", $host['coordinates']);

                $host['services'] = [];

                $this->points['hosts'][$row->name] = $host;
            }

            if ($row->service->id !== null) {
                $service = $this->populateObjectColumnsToArray($row->service);
                $this->points['hosts'][$hostname]['services'][$row->service->display_name] = $service;
            }
        }

        // remove hosts without problems and services
        if ($this->onlyProblems) {
            foreach ($this->points['hosts'] as $name => $host) {
                if (empty($host['services']) && $host['host_problem'] !== 1) {
                    unset($this->points['hosts'][$name]);
                }
            }
        }
    }

    private function addIcingadbServicesToPoints()
    {
        $serviceQuery = Service::on($this->icingadbUtils->getDb())
            ->columns([
                'id',
                'name',
                'display_name',
                'vars.geolocation',
                'vars.map_icon',
                'state.is_acknowledged',
                'state.hard_state',
                'state.soft_state',
                'state.last_state_change',
                'state.is_acknowledged',
                'state.in_downtime',
                'state.is_problem',
                'host.id',
                'host.name',
                'host.display_name',
                'host.state.is_acknowledged',
                'host.state.hard_state',
                'host.state.soft_state',
                'host.state.last_state_change',
                'host.state.is_acknowledged',
                'host.state.in_downtime',
                'host.state.is_problem',
            ])
            ->filter(IplFilter::like('service.vars.geolocation', '*'))
            ->setResultSetClass(VolatileStateResults::class);

        if ($this->filter) {
            $serviceQuery->Filter($this->filter);
        }

        if ($this->onlyProblems) {
            $serviceQuery->Filter(IplFilter::equal('service.state.is_problem', 'y'));
        }

        $this->icingadbUtils->applyRestrictions($serviceQuery);
        $serviceQuery = $serviceQuery->execute();

        if (! $serviceQuery->hasResult()) {
            return;
        }

        foreach ($serviceQuery as $row) {
            if (! preg_match($this->coordinatePattern, $row->vars['geolocation'])) {
                continue;
            }

            $identifier = $row->host->name . "!" . $row->name;
            $host = $this->populateObjectColumnsToArray($row->host);
            $host['coordinates'] = $row->vars['geolocation'];
            $host['icon'] = $row->vars['map_icon'] ?? null;
            $host['coordinates'] = explode(",", $host['coordinates']);

            $service = $this->populateObjectColumnsToArray($row);

            $host['services'][$row->display_name] = $service;

            $this->points['services'][$identifier] = $host;
        }
    }

    /**
     * @param Model $object
     *
     * @return array
     */
    private function populateObjectColumnsToArray(Model $object)
    {
        $objectType = $object instanceof Service ? 'service' : 'host';

        $stateColumn = $this->stateColumn;
        $lastStateChangeColumn = $this->stateChangeColumn;

        $obj = [];

        $obj["{$objectType}_display_name"]        = $object->display_name;
        $obj["{$objectType}_name"]                = $object->name;
        $obj["{$objectType}_acknowledged"]        = $object->state->is_acknowledged ? 1 : 0;
        $obj["{$objectType}_state"]               = $object->state->$stateColumn;
        $obj["{$objectType}_last_state_change"]   = $object->state->$lastStateChangeColumn;
        $obj["{$objectType}_in_downtime"]         = $object->state->in_downtime ? 1 : 0;

        return $obj;
    }
}
