<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Controllers;

use Icinga\Module\Map\Forms\Config\GeneralConfigForm;
use Icinga\Module\Map\Forms\Config\DirectorConfigForm;
use Icinga\Web\Widget\Tabs;
use ipl\Html\HtmlString;
use ipl\Web\Compat\CompatController;

final class ConfigController extends CompatController
{
    private const DEFAULTS = [
        'default_zoom'          => '4',
        'default_long'          => '13.377485',
        'default_lat'           => '52.515855',
        'min_zoom'              => '2',
        'max_zoom'              => '19',
        'max_native_zoom'       => '19',
        'cluster_problem_count' => 0,
        'popup_mouseover'       => 0,
        'tile_url'              => '//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    ];

    public function indexAction(): void
    {
        $this->assertPermission('config/modules');
        $this->renderConfigForm(new GeneralConfigForm(), 'config');
    }

    public function directorAction(): void
    {
        $this->assertPermission('map/director/configuration');
        $this->renderConfigForm(new DirectorConfigForm(), 'director');
    }

    private function renderConfigForm(object $form, string $tabName): void
    {
        $form->setIniConfig($this->Config());
        $form->handleRequest();

        $this->addContent(HtmlString::create($form));
        $this->mergeTabs($this->Module()->getConfigTabs());
        $this->getTabs()->activate($tabName);
    }

    public function fetchAction(): never
    {
        $type = strtolower($this->params->shift('type', ''));
        $moduleConfig = $this->Config();

        $defaults = self::DEFAULTS;
        $defaults['disable_cluster_at_zoom'] = (int) $defaults['max_zoom'] - 1;

        // Override with user preferences
        $userPreferences = $this->Auth()->getUser()->getPreferences();
        
        if ($userPreferences->has('map')) {
            $moduleConfig->getSection('map')->merge($userPreferences->get('map'));
        }

        if ($type === 'director') {
            $moduleConfig->getSection('map')->merge($moduleConfig->getSection('director'));

            if ($userPreferences->has('map-director')) {
                $moduleConfig->getSection('map')->merge($userPreferences->get('map-director'));
            }
        }

        $config = [];
        foreach ($defaults as $parameter => $default) {
            $config[$parameter] = $moduleConfig->get('map', $parameter, $default);
        }

        $this->outputJson($config);
    }

    private function mergeTabs(Tabs $tabs): void
    {
        foreach ($tabs->getTabs() as $tab) {
            $this->getTabs()->add($tab->getName(), $tab);
        }
    }

    private function outputJson(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit();
    }
}
