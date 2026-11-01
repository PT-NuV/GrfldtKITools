<?php

/**
 * Grfldt KI Tools für IPSView
 *
 * Liest und ändert IPSView-Ansichten (Medienobjekte vom Typ IPSView) über einfache JSON-Befehle.
 * Befehle kommen über die Variable "Befehl" (RequestAction) oder über GKT_Execute(InstanzID, JSON).
 * Das Ergebnis steht danach als JSON in der Variable "Ergebnis".
 *
 * Vor jeder schreibenden Änderung wird der bisherige Stand als Sicherung (Dokument-Medienobjekt
 * unterhalb dieser Instanz) abgelegt. Änderungen werden vor dem Speichern geprüft.
 *
 * Eigene Implementierung des Dateiformats, kein Code aus IPSView übernommen.
 * Inoffiziell: weder von IPSView noch von Symcon unterstützt. Getestet mit IPSView 6.5 und Symcon 9.1 Rust Edition (Beta).
 *
 * Autor: Cornelius Neufeldt (im Symcon-Forum: Garfieldt)
 * Entwickelt mit Unterstützung von Claude (KI-Assistent von Anthropic): Das Dateiformat wurde gemeinsam
 * durch Analyse von Ansichten und des IPSView-Clients erschlossen, der Code wurde von Claude geschrieben.
 * Ab Version 1.3 nach der Datenmodell-Referenz von Andreas Brauneis (Entwickler von IPSView).
 *
 * Lizenz: MIT (siehe LICENSE)
 */
class GrfldtKITools extends IPSModule
{
    private const VERSION = '1.5';
    private const IVC_MODULE_ID = '{A9AD5292-2244-15E7-6007-C7B75164AE00}';

    // Reguläre Elementtypen laut Datenmodell-Referenz von IPSView 6.5 (dürfen neu angelegt werden)
    private const CONTROL_TYPES = [
        'IPSVarLabel', 'IPSMarquee', 'IPSTxtLabel', 'IPSTextBox', 'IPSCustomControl', 'IPSCheckBox', 'IPSSwitch',
        'IPSViewContainer', 'IPSInlinePage', 'IPSHTMLBox', 'IPSWebView', 'IPSMedia', 'IPSMediaStream', 'IPSDoorIPMedia',
        'IPSRGBBox', 'IPSRGBButton', 'IPSShapeLineH', 'IPSShapeLineV', 'IPSShapeRect', 'IPSShapeCircle', 'IPSFlowLine',
        'IPSFlowText', 'IPSShadow', 'IPSProgressbar', 'IPSProgressbarVertical', 'IPSSlider', 'IPSSliderVertical',
        'IPSRangeSlider', 'IPSRangeSliderVertical', 'IPSRGBSlider', 'IPSRGBSliderVertical', 'IPSWidgetRGBCircleSlider',
        'IPSWidgetCircleSlider', 'IPSWidgetCircleRangeSlider', 'IPSWidgetGauge', 'IPSWidgetMediaChart',
        'IPSWidgetPieChart', 'IPSWidgetIPSViewChart', 'IPSWidgetColor', 'IPSWidgetCiePicker', 'IPSWidgetMessages',
        'IPSWidgetTimer', 'IPSWidgetWeekplan', 'IPSWidgetTileHtml', 'IPSButton', 'IPSToggleButton',
        'IPSAssociationButton', 'IPSContinuousButton', 'IPSLongPressButton', 'IPSRangeButton', 'IPSValueButton',
        'IPSInlineButton', 'IPSDetailButton', 'IPSDoorIPButton', 'IPSScriptButton', 'IPSImage', 'IPSUrlImage',
        'IPSImageButton', 'IPSToggleImage', 'IPSAssociationImage', 'IPSContinuousImage', 'IPSLongPressImage',
        'IPSRangeImage', 'IPSValueImage', 'IPSInlineImage', 'IPSDetailImage', 'IPSDoorIPImage', 'IPSScriptImage'
    ];

    // Ältere bzw. nicht reguläre Typen: in bestehenden Ansichten geduldet, aber nicht neu anlegbar
    private const LEGACY_CONTROL_TYPES = [
        'IPSMediaImage', 'IPSTabBar', 'IPSDimout', 'IPSWidgetCalendar', 'IPSWidgetChartIPSView', 'IPSWidgetChartSymcon'
    ];

    // Interne Icons von IPSView (Bildnummer => Name), verwendbar als Image1/Image2/PageImage
    private const INTERNAL_ICONS = [
        10 => 'Image', 1000 => 'Transparent', 1001 => 'Home', 1002 => 'Power', 1003 => 'Schedule', 1004 => 'Settings',
        1005 => 'Line Chart', 1006 => 'Plus', 1007 => 'Minus', 1008 => 'Bar Chart', 1009 => 'Autorenew',
        1010 => 'Dashboard', 1011 => 'Calendar Today', 1012 => 'Done', 1013 => 'Event', 1014 => 'Fingerprint',
        1015 => 'Lock Closed', 1016 => 'Lock Open', 1017 => 'Arrow Down', 1018 => 'Arrow Left', 1019 => 'Arrow Right',
        1020 => 'Arrow Up', 1021 => 'Menu', 1022 => 'Return', 1023 => 'Backspace', 1024 => 'Title', 1025 => 'Clear',
        1026 => 'Euro', 1027 => 'Compare', 1028 => 'Call', 1029 => 'Call End', 1030 => 'Phone Disabled',
        1031 => 'Phone Missed', 1032 => 'Call Log',
        2001 => 'Error', 2002 => 'Warning', 2003 => 'Alarm', 2004 => 'Alarm Off', 2005 => 'Alarm On',
        2006 => 'Notifications', 2007 => 'Notifications Active', 2008 => 'Notifications Off', 2009 => 'Info',
        2010 => 'Battery Alert', 2011 => 'Battery Charging Full', 2013 => 'Battery', 2014 => 'Battery Unknown',
        2015 => 'Security',
        3001 => 'Camera Indoor', 3002 => 'Camera Outdoor', 3003 => 'Shutter', 3005 => 'Bulb', 3006 => 'Lamp',
        3007 => 'Light', 3008 => 'Thermostat', 3009 => 'Door', 3010 => 'Window', 3011 => 'TV', 3012 => 'Tablet',
        3013 => 'Phone', 3014 => 'Laptop', 3015 => 'Sensor', 3016 => 'Person Run', 3017 => 'Person', 3018 => 'Water',
        3019 => 'Router', 3020 => 'Cloud', 3021 => 'Wifi', 3022 => 'Signal', 3023 => 'Image', 3024 => 'Stream',
        3025 => 'Network', 3026 => 'Mail', 3027 => 'Storage', 3028 => 'Dryer', 3029 => 'Air', 3030 => 'Speed',
        3031 => 'Bolt', 3032 => 'Remote', 3033 => 'Sun', 3034 => 'Directions', 3035 => 'AC Unit',
        3036 => 'Electric Car', 3037 => 'Pool', 3038 => 'Electric Meter', 3039 => 'Solar Power',
        3040 => 'Electrical Services', 3041 => 'Heat Pump', 3042 => 'Wind Power', 3043 => 'Hot Tub',
        3044 => 'Charging Station', 3045 => 'Water Pump', 3046 => 'Gas Meter', 3047 => 'Light Group',
        3048 => 'Detector', 3049 => 'Detector Smoke', 3050 => 'Detector Alarm', 3051 => 'Detector Co', 3052 => 'Power',
        3053 => 'Power Off', 3054 => 'EV Charger', 3055 => 'Mode Fan', 3056 => 'Humidity', 3057 => 'Scene',
        3058 => 'Water Heater', 3059 => 'Gauge Meter', 3060 => 'Dew Point', 3061 => 'Sprinkler', 3062 => 'Valve',
        4001 => 'Shower', 4002 => 'Bathroom', 4003 => 'Kitchen', 4006 => 'Couch', 4007 => 'Grill', 4008 => 'Fireplace',
        4009 => 'Bedroom Parent', 4010 => 'Bedroom Child', 4011 => 'Bedroom Baby', 4012 => 'Smoking', 4013 => 'Dinner',
        4014 => 'Garage', 4015 => 'WC', 4016 => 'Stairs', 4017 => 'Checkroom', 4018 => 'Child', 4019 => 'Family',
        4020 => 'Iron', 4021 => 'Microwave', 4022 => 'Spa', 4023 => 'Deck', 4024 => 'Nature', 4025 => 'Flower',
        4026 => 'Park', 4027 => 'Moon', 4028 => 'Cocktail', 4029 => 'Science', 4030 => 'Construction',
        4031 => 'Factory', 4032 => 'Dishwasher', 4033 => 'Washer', 4034 => 'PowerDrill', 4035 => 'House',
        5001 => 'Fast Forward', 5002 => 'Fast Rewind', 5003 => 'Record', 5004 => 'Pause', 5005 => 'Play',
        5006 => 'Skip Previous', 5007 => 'Skip Next', 5008 => 'Stop', 5009 => 'Videocam', 5010 => 'Videocam Off',
        5011 => 'Volume Down', 5012 => 'Volume Mute', 5013 => 'Volume Off', 5014 => 'Volume Up', 5015 => 'Speaker',
        5016 => 'Speaker Group', 5017 => 'Eject',
        6001 => 'Toggle On', 6002 => 'Toggle Off', 6003 => 'Radio Button Unchecked', 6004 => 'Radio Button Checked',
        6005 => 'Check Box', 6006 => 'Check Box Blank', 6007 => 'Indeterminate Check Box'
    ];

    // Bildnummern unterhalb dieser Grenze behandelt der Client als interne Icons
    private const ICON_LIMIT = 1000000;

    // Erlaubte Größenanpassung (ResizeOption) je Seitenart
    private const RESIZE_OPTIONS = [
        'main' => [0, 1], 'alternate' => [0, 1], 'alternateMain' => [0, 1], 'popup' => [0, 1, 5],
        'standard' => [0, 2, 3, 4, 6, 11, 61]
    ];

    // Schlüssel, die ein Element in der Ansicht haben kann
    private const CONTROL_KEYS = [
        'Type', 'ID', 'IDIdent', 'OnDownID', 'OnPressedID', 'OnReleasedID', 'Name', 'Text1', 'Text2', 'Image1', 'Image2',
        'Width', 'Height', 'LocationX', 'LocationY', 'Rotation', 'CR1', 'CR2', 'CR3', 'CR4', 'BW', 'AutoSize', 'TextAlign',
        'Readonly', 'AllowZoom', 'Reverse', 'Synchronize', 'SyncOption', 'ValueSelected', 'ValueInt', 'Min', 'Max', 'Step',
        'Digits', 'Prefix', 'Suffix', 'InlineMain', 'InlineHeader', 'InlineMenu', 'InlineNavigation', 'InlineDetail',
        'InlineContent', 'InlineInfo', 'InlinePopup1', 'InlinePopup2', 'InlinePopup3', 'AlternatePage',
        'ForeColor1', 'ForeColor2', 'BackColor1', 'BackColor2', 'BorderColor1', 'Font', 'Remote', 'ScriptVars',
        'WidgetVars', 'Associations', 'Widget', 'WidgetDetail', 'WidgetDetails', 'AliasIDs', 'AlternateIDs',
        'LongPressIDs', 'CustomProperties', 'IsSelected', 'ResizeFactor', 'ItemID', 'ItemParentID', 'ItemIdx',
        'ItemName', 'ItemParent', 'ItemExpanded', 'ItemLayer', 'ItemVisibility'
    ];

    private const COLOR_KEYS = ['ForeColor1', 'ForeColor2', 'BackColor1', 'BackColor2', 'BorderColor1'];

    private const INLINE_KEYS = [
        'InlineMain', 'InlineHeader', 'InlineMenu', 'InlineNavigation', 'InlineDetail',
        'InlineContent', 'InlineInfo', 'InlinePopup1', 'InlinePopup2', 'InlinePopup3', 'AlternatePage'
    ];

    // Containertyp (gespeichert im Schlüssel "Digits" eines IPSInlinePage)
    private const CONTAINER_TYPES = [
        'main' => 1, 'header' => 2, 'menu' => 3, 'navigation' => 4, 'detail' => 5,
        'content' => 6, 'info' => 7, 'popup' => 8, 'detailmenu' => 9, 'footer' => 10
    ];
    private const CONTAINER_INLINE_KEY = [
        1 => 'InlineMain', 2 => 'InlineHeader', 3 => 'InlineMenu', 4 => 'InlineNavigation', 5 => 'InlineDetail',
        6 => 'InlineContent', 7 => 'InlineInfo', 8 => 'InlinePopup1', 9 => 'InlinePopup2', 10 => 'InlinePopup3'
    ];

    // Elemente, die Werte setzen (für UsedIDs)
    private const WRITING_TYPES = [
        'IPSSwitch', 'IPSCheckBox', 'IPSSlider', 'IPSRangeSlider', 'IPSRGBSlider', 'IPSToggleButton', 'IPSToggleImage',
        'IPSValueButton', 'IPSValueImage', 'IPSRangeButton', 'IPSRangeImage', 'IPSRGBButton', 'IPSAssociationButton',
        'IPSAssociationImage', 'IPSContinuousButton', 'IPSContinuousImage', 'IPSTextBox', 'IPSWidgetCircleSlider',
        'IPSWidgetCircleRangeSlider', 'IPSWidgetRGBCircleSlider', 'IPSWidgetColor', 'IPSWidgetCiePicker'
    ];

    private const BACKGROUND_LAYOUTS = ['', 'None', 'Tile', 'Center', 'Stretch', 'Zoom'];

    // Ansichts-Einstellungen, die nicht über setView geändert werden dürfen
    private const VIEW_PROTECTED = [
        'Pages', 'Images', 'ID', 'ItemID', 'UsedIDs', 'AuthPassword', 'LicenseKey', 'LicenseRegister',
        'LicenseMajorVersion', 'LicenseMinorVersion', 'LicenseDemoDate', 'LicenseDemoKey', 'LicenseDemoCrypt', 'LicenseType'
    ];

    private const PAGE_DEFAULTS = [
        'ParentName' => '', 'PageName' => '', 'RepositoryName' => '', 'RepositoryGroup' => '', 'PageTitle' => '',
        'PageImage' => 0, 'Idx' => 0, 'Position' => 0, 'IsExpanded' => false, 'IsCustom' => false, 'Description' => '',
        'IsPageProtected' => false, 'ResizeOption' => 0, 'GridRowSpan' => 1, 'GridColSpan' => 1, 'IsMainPage' => false,
        'IsFolder' => false, 'IsPopup' => false, 'IsInline' => true, 'IsAlternatePage' => false, 'PopupWidth' => 0,
        'PopupHeight' => 0, 'PopupAutoClose' => false, 'BackgroundImage' => 0, 'BackgroundLayout' => '',
        'GestureWipeLeftAction' => 0, 'GestureWipeLeftParam' => '', 'GestureWipeRightAction' => 0,
        'GestureWipeRightParam' => '', 'GestureWipeUpAction' => 0, 'GestureWipeUpParam' => '',
        'GestureWipeDownAction' => 0, 'GestureWipeDownParam' => '', 'DefaultLayer' => 2, 'GridEnabled' => false,
        'GridCellWidth' => '', 'GridBlockOption' => 0, 'GridPositionAnimationType' => 0, 'GridPositionAnimationSpeed' => 500,
        'GridVisibleAnimationType' => 0, 'GridVisibleAnimationSpeed' => 300, 'ContainerSource1' => 0,
        'ContainerTarget1' => 0, 'ContainerSource2' => 0, 'ContainerTarget2' => 0, 'AlternatePageSuffix' => '',
        'ConditionOrientation' => '', 'ConditionWidth' => '', 'ConditionHeight' => '', 'ConditionConfig' => '',
        'TemplateReference' => '', 'HelpText' => ''
    ];

    // Weitere reguläre Seiten-Eigenschaften, die nur bei Bedarf gesetzt werden (fehlend = von der Ansicht erben)
    private const PAGE_OPTIONAL_KEYS = [
        'BackColor', 'IsAlternateMain', 'IsCustomControl', 'IsCustomFolder', 'AlternateFactor',
        'GridMarginLeft', 'GridMarginTop', 'GridMarginRight', 'GridMarginBottom', 'GridCellHeight', 'GridCellSpacing',
        'GridLineEnabled', 'GridLineWidth', 'GridLineColor', 'GridBlockBackColor', 'GridBlockBorderColor',
        'GridBlockMargin', 'GridBlockCR1', 'GridBlockCR2', 'GridBlockCR3', 'GridBlockCR4'
    ];

    // ---------------------------------------------------------------------------------------------
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('BackupKeep', 20);
        // Stand jeder Ansicht nach dem letzten Speichern durch das Modul (erkennt Überschreiben durch den Designer)
        $this->RegisterAttributeString('SavedStates', '{}');
        $this->RegisterVariableString('Command', 'Befehl', '', 1);
        $this->RegisterVariableString('Result', 'Ergebnis', '', 2);
        $this->EnableAction('Command');
        // Freigabe durch den Nutzer: Neue Ansichten liegen außerhalb der Instanz und werden nur angelegt,
        // wenn der Nutzer diesen Schalter einschaltet. Er schaltet sich nach dem Anlegen wieder aus.
        $this->RegisterVariableBoolean('AllowCreateView', 'Neue Ansicht anlegen erlauben', '~Switch', 3);
        $this->EnableAction('AllowCreateView');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        foreach (['Command', 'Result'] as $ident) {
            $id = @$this->GetIDForIdent($ident);
            if ($id) {
                IPS_SetHidden($id, true);
            }
        }
        $this->SetStatus(102);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'AllowCreateView') {
            $this->SetValue('AllowCreateView', (bool) $Value);
            return;
        }
        if ($Ident !== 'Command') {
            throw new Exception('Unbekannte Aktion: ' . $Ident);
        }
        $this->SetValue('Command', mb_substr((string) $Value, 0, 4000));
        $this->SetValue('Result', $this->Execute((string) $Value));
    }

    /**
     * Führt einen Befehl aus und gibt das Ergebnis als JSON zurück.
     */
    public function Execute(string $Command): string
    {
        $cmd = json_decode($Command, true);
        $out = ['ok' => false, 'id' => null, 'time' => date('c')];
        try {
            if (!is_array($cmd)) {
                throw new Exception('Befehl ist kein gültiges JSON: ' . json_last_error_msg());
            }
            $out['id'] = $cmd['id'] ?? null;
            $out['result'] = $this->Dispatch($cmd);
            $out['ok'] = true;
        } catch (Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    // ---------------------------------------------------------------------------------------------
    private function Dispatch(array $cmd)
    {
        switch ($cmd['cmd'] ?? '') {
            case 'ping':
                return ['module' => 'GrfldtKITools', 'version' => self::VERSION];
            case 'describe':
                return $this->Describe();
            case 'listViews':
                return $this->ListViewObjects();
            case 'outline':
                return $this->Outline($this->RequireInt($cmd, 'view'), $cmd['page'] ?? null);
            case 'getControls':
                return $this->GetControlsCmd($this->RequireInt($cmd, 'view'), $this->RequireString($cmd, 'page'), $cmd['items'] ?? []);
            case 'getPage':
                return $this->GetPageCmd($this->RequireInt($cmd, 'view'), $this->RequireString($cmd, 'page'));
            case 'getView':
                return $this->GetViewCmd($this->RequireInt($cmd, 'view'), $cmd['keys'] ?? []);
            case 'apply':
                return $this->Apply($this->RequireInt($cmd, 'view'), $cmd['ops'] ?? [], !empty($cmd['dryRun']), !empty($cmd['force']));
            case 'createView':
                return $this->CreateViewCmd($this->RequireString($cmd, 'name'), $this->RequireInt($cmd, 'template'));
            case 'backups':
                return $this->ListBackups($this->RequireInt($cmd, 'view'));
            case 'restore':
                return $this->Restore($this->RequireInt($cmd, 'view'), $this->RequireInt($cmd, 'backup'));
            case 'resetCache':
                return $this->ResetClientCache();
            case 'icons':
                return self::INTERNAL_ICONS;
            default:
                throw new Exception('Unbekannter Befehl "' . ($cmd['cmd'] ?? '') . '". Mit {"cmd":"describe"} gibt es eine Übersicht.');
        }
    }

    private function Describe(): array
    {
        return [
            'commands' => [
                'ping' => '{}', 'describe' => '{}', 'listViews' => '{}',
                'outline' => '{view, page?}', 'getControls' => '{view, page, items:[ItemID]}',
                'getPage' => '{view, page}', 'getView' => '{view, keys?:[]}',
                'apply' => '{view, ops:[...], dryRun?, force?}', 'createView' => '{name, template} (nur nach Freigabe durch den Nutzer)',
                'backups' => '{view}', 'restore' => '{view, backup}', 'resetCache' => '{}',
                'icons' => '{} (interne Icons: Nummer => Name)'
            ],
            'ops' => [
                'setView' => '{values:{}}',
                'addPage' => '{name, title?, kind?: standard|popup|alternateMain, width?, height?, values?}',
                'setPage' => '{page, values:{}}',
                'renamePage' => '{page, to}',
                'deletePage' => '{page}',
                'addControls' => '{page, controls:[{Type,...,ref?}]}',
                'updateControls' => '{page, items:[ItemID], values:{}} oder {page, updates:[{item, values}]}',
                'deleteControls' => '{page, items:[ItemID]}',
                'moveControls' => '{page, items:[ItemID]|"all", dx, dy}',
                'transferControls' => '{from, to, items:[ItemID]|"all", dx?, dy?}',
                'copyControls' => '{from, to?, items:[ItemID]|"all", dx?, dy?, replaceIds?:{alt:neu}, replaceText?:{alt:neu}, ref?}',
                'copyPage' => '{page, to, title?, replaceIds?:{alt:neu}, replaceText?:{alt:neu}}',
                'addImage' => '{media? | file? | data? (Base64 PNG/JPG), ref?}',
                'setBackground' => '{page, media? | file? | hash?, layout?}'
            ],
            'colors' => 'Farben als "#RRGGBB" oder "#AARRGGBB": ' . implode(', ', self::COLOR_KEYS),
            'containerTypes' => self::CONTAINER_TYPES,
            'backgroundLayouts' => self::BACKGROUND_LAYOUTS,
            'resizeOptions' => self::RESIZE_OPTIONS,
            'controlTypes' => self::CONTROL_TYPES,
            'hints' => [
                'Image1/Image2/PageImage/Associations[].Image: Bildkennung, interne Icon-Nummer, "icon:Name" (siehe icons) oder "ref:Name" (addImage mit ref weiter vorne in derselben Anweisung)',
                '"ref:Name" geht auch für ItemIDs aus addControls mit ref (z. B. in items)',
                'Werte mit Profil: IPSVarLabel mit Step 6 anlegen (Einheit und Format aus dem Profil)',
                'Widget: Schlüssel klein, Werte als Text; das Modul wandelt das automatisch um',
                'TextAlign: 1–9 = oben/mitte/unten × links/mittig/rechts; bei IPSInlinePage Scrollrichtung 0/1/2',
                'Step bei IPSVarLabel = Darstellungsart: 0 Wert, 1 Zuordnungen, 2 Prozent, 3–5 Zeitstempel, 6 Symcon (100 Text nur bei IPSFlowText); bei 0 Digits für Nachkommastellen setzen',
                'ItemLayer: 1 Hintergrund, 2 Mitte, 3 Vordergrund, 4 Overlay'
            ]
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // Lesen

    private function ListViewObjects(): array
    {
        $list = [];
        foreach (IPS_GetMediaListByType(0) as $id) {
            $m = IPS_GetMedia($id);
            $list[] = ['id' => $id, 'name' => IPS_GetName($id), 'size' => $m['MediaSize'], 'updated' => date('c', $m['MediaUpdated'])];
        }
        return $list;
    }

    private function Outline(int $viewID, $pageName): array
    {
        $view = $this->LoadView($viewID);
        $pages = [];
        foreach ($view->Pages as $p) {
            if ($pageName !== null && $p->PageName !== $pageName) {
                continue;
            }
            $ctrls = [];
            foreach ($p->Controls ?? [] as $c) {
                $label = $c->Text1 ?? '';
                $id = (int) ($c->ID ?? 0);
                if ($id > 0 && IPS_ObjectExists($id)) {
                    $label = trim($label . ' {' . IPS_GetName(IPS_GetParent($id)) . '/' . IPS_GetName($id) . '}');
                }
                $extra = [];
                foreach (self::INLINE_KEYS as $k) {
                    if (!empty($c->$k)) {
                        $extra[$k] = $c->$k;
                    }
                }
                $row = [
                    $c->ItemID ?? null, $c->Type ?? '?', $id,
                    round((float) ($c->LocationX ?? 0), 1), round((float) ($c->LocationY ?? 0), 1),
                    round((float) ($c->Width ?? 0), 1), round((float) ($c->Height ?? 0), 1), $label
                ];
                if ($extra) {
                    $row[] = $extra;
                }
                $ctrls[] = $row;
            }
            $pages[] = [
                'PageName' => $p->PageName, 'PageTitle' => $p->PageTitle ?? '', 'kind' => $this->PageKind($p),
                'BackgroundImage' => $p->BackgroundImage ?? 0, 'BackgroundLayout' => $p->BackgroundLayout ?? '',
                'controls' => $ctrls
            ];
        }
        $images = [];
        foreach ($view->Images ?? [] as $img) {
            $images[] = ['hash' => $img->ImageHash, 'type' => $img->ImageType, 'w' => $img->Width ?? null, 'h' => $img->Height ?? null];
        }
        $out = [
            'view' => ['id' => $viewID, 'Name' => $view->Name ?? '', 'Width' => $view->Width ?? null, 'Height' => $view->Height ?? null,
                'Hardware' => $view->Hardware ?? '', 'Orientation' => $view->Orientation ?? '', 'ViewVersion' => $view->ViewVersion ?? null,
                'ItemID' => $view->ItemID ?? null],
            'columns' => ['ItemID', 'Type', 'ID', 'X', 'Y', 'W', 'H', 'Text {Parent/Objekt}', 'Menüziele'],
            'pages' => $pages,
            'images' => $images
        ];
        $ext = $this->ExternalChange($viewID, $view);
        if ($ext !== null) {
            $out['externalChange'] = $ext;
        }
        return $out;
    }

    private function GetControlsCmd(int $viewID, string $pageName, array $items): array
    {
        $view = $this->LoadView($viewID);
        $page = $this->FindPage($view, $pageName);
        $result = [];
        foreach ($page->Controls ?? [] as $c) {
            if (!$items || in_array($c->ItemID ?? null, $items, true)) {
                $result[] = $c;
            }
        }
        return $result;
    }

    private function GetPageCmd(int $viewID, string $pageName)
    {
        $page = clone $this->FindPage($this->LoadView($viewID), $pageName);
        $page->Controls = count($page->Controls ?? []) . ' Elemente';
        return $page;
    }

    private function GetViewCmd(int $viewID, array $keys): array
    {
        $view = $this->LoadView($viewID);
        $out = [];
        foreach ($view as $k => $v) {
            if (in_array($k, ['Pages', 'Images'], true) || strpos($k, 'License') === 0 || $k === 'AuthPassword') {
                continue;
            }
            if ($keys && !in_array($k, $keys, true)) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    // ---------------------------------------------------------------------------------------------
    // Ändern

    private function Apply(int $viewID, array $ops, bool $dryRun, bool $force): array
    {
        if (!$ops) {
            throw new Exception('Keine Operationen angegeben (ops).');
        }
        $original = IPS_GetMediaContent($viewID);
        $view = $this->LoadView($viewID);
        $external = $this->ExternalChange($viewID, $view);
        $refs = [];
        $log = [];
        foreach ($ops as $n => $op) {
            try {
                $log[] = $this->ApplyOp($view, $op, $refs);
            } catch (Throwable $e) {
                throw new Exception('Operation ' . ($n + 1) . ' (' . ($op['op'] ?? '?') . '): ' . $e->getMessage());
            }
        }
        $this->Reindex($view);
        $this->UpdateUsedIDs($view);
        $problems = $this->Validate($view);
        if ($problems && !$force) {
            throw new Exception('Prüfung fehlgeschlagen, nichts gespeichert: ' . implode(' | ', array_slice($problems, 0, 25)));
        }
        $result = ['refs' => $refs, 'log' => $log, 'warnings' => $problems, 'saved' => false];
        $hints = $this->Hints($view);
        if ($hints) {
            $result['hints'] = $hints;
        }
        if ($external !== null) {
            $result['externalChange'] = $external;
        }
        if ($dryRun) {
            return $result;
        }
        $result['backup'] = $this->CreateBackup($viewID, $original);
        $this->SaveView($viewID, $view);
        $this->RememberState($viewID);
        $result['saved'] = true;
        $result['cacheReset'] = $this->ResetClientCache();
        return $result;
    }

    private function ApplyOp(object $view, array $op, array &$refs): string
    {
        // Verweise "ref:Name" auf Bilder (addImage) oder Elemente (addControls) aus derselben Anweisung auflösen
        if (($op['op'] ?? '') !== 'addImage') {
            $op = $this->ResolveRefs($op, $refs);
        }
        switch ($op['op'] ?? '') {
            case 'setView':
                foreach ($this->RequireArray($op, 'values') as $k => $v) {
                    if (in_array($k, self::VIEW_PROTECTED, true)) {
                        throw new Exception('Einstellung "' . $k . '" ist geschützt.');
                    }
                    if (!property_exists($view, $k)) {
                        throw new Exception('Unbekannte Ansichts-Einstellung "' . $k . '".');
                    }
                    $view->$k = (is_string($v) && isset($v[0]) && $v[0] === '#') ? $this->Color($v) : $this->ToJsonValue($v);
                }
                return 'Ansicht geändert';

            case 'addPage':
                $name = $this->RequireString($op, 'name');
                if ($this->FindPage($view, $name, false)) {
                    throw new Exception('Seite "' . $name . '" gibt es schon.');
                }
                $page = (object) self::PAGE_DEFAULTS;
                $page->PageName = $name;
                $page->PageTitle = $op['title'] ?? $name;
                $kind = $op['kind'] ?? 'standard';
                if ($kind === 'popup') {
                    $page->IsInline = false;
                    $page->IsPopup = true;
                    $page->PopupWidth = (float) ($op['width'] ?? 400);
                    $page->PopupHeight = (float) ($op['height'] ?? 300);
                } elseif ($kind === 'standard') {
                    // Standardseiten speichern ihre Größe ebenfalls in PopupWidth/PopupHeight;
                    // ohne Größe zeigt ein Seiten-Container nichts an.
                    $page->PopupWidth = (float) ($op['width'] ?? $view->Width ?? 0);
                    $page->PopupHeight = (float) ($op['height'] ?? $view->Height ?? 0);
                } elseif ($kind === 'alternateMain') {
                    // Bedingte alternative Hauptseite (z. B. für Smartphones); Bedingungen über values:
                    // ConditionWidth/ConditionHeight (z. B. "<700"), ConditionConfig, ConditionOrientation
                    $page->IsInline = false;
                    $page->IsAlternateMain = true;
                    $page->PopupWidth = (float) ($op['width'] ?? $view->Width ?? 0);
                    $page->PopupHeight = (float) ($op['height'] ?? $view->Height ?? 0);
                } else {
                    throw new Exception('Seitenart "' . $kind . '" wird nicht unterstützt (standard, popup, alternateMain).');
                }
                $maxPos = 0;
                foreach ($view->Pages as $p) {
                    $maxPos = max($maxPos, (int) ($p->Position ?? 0));
                }
                $page->Position = $maxPos + 10;
                foreach ($op['values'] ?? [] as $k => $v) {
                    $this->SetPageValue($page, $k, $v);
                }
                $page->Controls = [];
                $page->MatchingProfiles = [];
                $page->Properties = [];
                $view->Pages[] = $page;
                return 'Seite "' . $name . '" angelegt';

            case 'setPage':
                $page = $this->FindPage($view, $this->RequireString($op, 'page'));
                foreach ($this->RequireArray($op, 'values') as $k => $v) {
                    $this->SetPageValue($page, $k, $v);
                }
                return 'Seite "' . $page->PageName . '" geändert';

            case 'renamePage':
                $page = $this->FindPage($view, $this->RequireString($op, 'page'));
                $old = $page->PageName;
                $new = $this->RequireString($op, 'to');
                if ($this->FindPage($view, $new, false)) {
                    throw new Exception('Seite "' . $new . '" gibt es schon.');
                }
                $page->PageName = $new;
                $count = $this->ReplacePageReferences($view, $old, $new);
                return 'Seite umbenannt, ' . $count . ' Verweise angepasst';

            case 'deletePage':
                $name = $this->RequireString($op, 'page');
                $page = $this->FindPage($view, $name);
                if (!empty($page->IsMainPage)) {
                    throw new Exception('Die Hauptseite kann nicht gelöscht werden.');
                }
                $view->Pages = array_values(array_filter($view->Pages, function ($p) use ($name) {
                    return $p->PageName !== $name;
                }));
                return 'Seite "' . $name . '" gelöscht';

            case 'addControls':
                $page = $this->FindPage($view, $this->RequireString($op, 'page'));
                $added = [];
                foreach ($this->RequireArray($op, 'controls') as $def) {
                    $ref = $def['ref'] ?? null;
                    unset($def['ref']);
                    $c = $this->BuildControl($view, $def);
                    $page->Controls[] = $c;
                    $added[] = $c->ItemID;
                    if ($ref !== null) {
                        $refs[$ref] = $c->ItemID;
                    }
                }
                return count($added) . ' Elemente angelegt (' . implode(',', $added) . ')';

            case 'updateControls':
                $page = $this->FindPage($view, $this->RequireString($op, 'page'));
                $updates = $op['updates'] ?? [];
                if (isset($op['items'])) {
                    foreach ($op['items'] as $item) {
                        $updates[] = ['item' => $item, 'values' => $op['values'] ?? []];
                    }
                }
                $n = 0;
                foreach ($updates as $u) {
                    $c = $this->FindControl($page, (int) $u['item']);
                    foreach ($u['values'] ?? [] as $k => $v) {
                        $this->SetControlValue($c, $k, $v);
                    }
                    $this->CheckVisuType($c);
                    $this->NormalizeChart($c);
                    $n++;
                }
                return $n . ' Elemente geändert';

            case 'deleteControls':
                $page = $this->FindPage($view, $this->RequireString($op, 'page'));
                $items = $this->ResolveItems($page, $op['items'] ?? []);
                $before = count($page->Controls);
                $page->Controls = array_values(array_filter($page->Controls, function ($c) use ($items) {
                    return !in_array($c->ItemID ?? null, $items, true);
                }));
                return ($before - count($page->Controls)) . ' Elemente gelöscht';

            case 'moveControls':
                $page = $this->FindPage($view, $this->RequireString($op, 'page'));
                $items = $this->ResolveItems($page, $op['items'] ?? []);
                foreach ($page->Controls as $c) {
                    if (in_array($c->ItemID ?? null, $items, true)) {
                        $this->Shift($c, (float) ($op['dx'] ?? 0), (float) ($op['dy'] ?? 0));
                    }
                }
                return count($items) . ' Elemente verschoben';

            case 'transferControls':
                $from = $this->FindPage($view, $this->RequireString($op, 'from'));
                $to = $this->FindPage($view, $this->RequireString($op, 'to'));
                $items = $this->ResolveItems($from, $op['items'] ?? []);
                $keep = [];
                $n = 0;
                foreach ($from->Controls as $c) {
                    if (in_array($c->ItemID ?? null, $items, true)) {
                        $this->Shift($c, (float) ($op['dx'] ?? 0), (float) ($op['dy'] ?? 0));
                        $to->Controls[] = $c;
                        $n++;
                    } else {
                        $keep[] = $c;
                    }
                }
                $from->Controls = $keep;
                return $n . ' Elemente von "' . $from->PageName . '" nach "' . $to->PageName . '" übertragen';

            case 'copyControls':
                $from = $this->FindPage($view, $this->RequireString($op, 'from'));
                $to = $this->FindPage($view, (string) ($op['to'] ?? $from->PageName));
                $items = $this->ResolveItems($from, $op['items'] ?? []);
                $new = [];
                foreach ($from->Controls as $c) {
                    if (in_array($c->ItemID ?? null, $items, true)) {
                        $copy = $this->CopyControl($view, $c, $op);
                        $this->Shift($copy, (float) ($op['dx'] ?? 0), (float) ($op['dy'] ?? 0));
                        $to->Controls[] = $copy;
                        $new[] = $copy->ItemID;
                    }
                }
                if (isset($op['ref'])) {
                    $refs[$op['ref']] = $new;
                }
                return count($new) . ' Elemente nach "' . $to->PageName . '" kopiert (' . implode(',', $new) . ')';

            case 'copyPage':
                $src = $this->FindPage($view, $this->RequireString($op, 'page'));
                $name = $this->RequireString($op, 'to');
                if ($this->FindPage($view, $name, false)) {
                    throw new Exception('Seite "' . $name . '" gibt es schon.');
                }
                if (!empty($src->IsMainPage)) {
                    throw new Exception('Die Hauptseite kann nicht kopiert werden.');
                }
                $page = json_decode(json_encode($src));
                $page->PageName = $name;
                $page->PageTitle = isset($op['title']) ? (string) $op['title'] : $this->ReplaceTexts((string) ($src->PageTitle ?? $name), $op);
                $maxPos = 0;
                foreach ($view->Pages as $p) {
                    $maxPos = max($maxPos, (int) ($p->Position ?? 0));
                }
                $page->Position = $maxPos + 10;
                $page->Controls = [];
                $new = [];
                foreach ($src->Controls as $c) {
                    $copy = $this->CopyControl($view, $c, $op);
                    $page->Controls[] = $copy;
                    $new[] = $copy->ItemID;
                }
                $view->Pages[] = $page;
                return 'Seite "' . $src->PageName . '" als "' . $name . '" kopiert (' . count($new) . ' Elemente'
                    . ($new ? ', ' . min($new) . '–' . max($new) : '') . ')';

            case 'addImage':
                $hash = $this->AddImage($view, $op);
                if (isset($op['ref'])) {
                    $refs[$op['ref']] = $hash;
                }
                return 'Bild eingefügt (Kennung ' . $hash . ')';

            case 'setBackground':
                $page = $this->FindPage($view, $this->RequireString($op, 'page'));
                $hash = isset($op['hash']) ? (int) $op['hash'] : $this->AddImage($view, $op);
                $page->BackgroundImage = $hash;
                $layout = $op['layout'] ?? 'Center';
                if (!in_array($layout, self::BACKGROUND_LAYOUTS, true)) {
                    throw new Exception('Unbekanntes Hintergrund-Layout "' . $layout . '".');
                }
                $page->BackgroundLayout = $layout;
                return 'Hintergrund von "' . $page->PageName . '" gesetzt (Kennung ' . $hash . ')';

            default:
                throw new Exception('Unbekannte Operation "' . ($op['op'] ?? '') . '".');
        }
    }

    private function BuildControl(object $view, array $def): object
    {
        $type = $def['Type'] ?? '';
        if (!in_array($type, self::CONTROL_TYPES, true)) {
            throw new Exception('Unbekannter Elementtyp "' . $type . '".');
        }
        // Komfort: Seiten-Container über "container" und "page" angeben
        if ($type === 'IPSInlinePage') {
            if (isset($def['container'])) {
                $def['Digits'] = $this->ContainerTypeValue($def['container']);
                unset($def['container']);
            }
            if (isset($def['page'])) {
                $def['Text1'] = $def['page'];
                unset($def['page']);
            }
        }
        // Komfort: Menü-Button über "target" (Containertyp) und "page"
        if ($type === 'IPSInlineButton' || $type === 'IPSInlineImage') {
            if (isset($def['page'])) {
                $key = self::CONTAINER_INLINE_KEY[$this->ContainerTypeValue($def['target'] ?? 'content')];
                $def[$key] = $def['page'];
                unset($def['page'], $def['target']);
            }
        }
        $itemID = (int) ($view->ItemID ?? 1);
        $view->ItemID = $itemID + 1;
        $c = new stdClass();
        $c->Type = $type;
        $c->ID = (int) ($def['ID'] ?? 0);
        $c->AutoSize = true;
        $c->Width = 100;
        $c->Height = 40;
        $c->LocationX = 0;
        $c->LocationY = 0;
        foreach ($def as $k => $v) {
            if ($k === 'Type') {
                continue;
            }
            $this->SetControlValue($c, $k, $v);
        }
        $this->CheckVisuType($c);
        $this->NormalizeChart($c);
        $c->ItemID = $itemID;
        $c->ItemName = preg_replace('/^IPS/', '', $type) . $itemID;
        $c->ItemIdx = 0;
        $c->ItemLayer = $c->ItemLayer ?? 2;
        $c->ItemVisibility = $c->ItemVisibility ?? 0;
        return $c;
    }

    private function SetControlValue(object $c, string $k, $v): void
    {
        if (in_array($k, ['ItemID', 'ItemIdx', 'Type'], true)) {
            throw new Exception('"' . $k . '" wird vom Modul verwaltet.');
        }
        if (!in_array($k, self::CONTROL_KEYS, true)) {
            throw new Exception('Unbekannte Element-Eigenschaft "' . $k . '".');
        }
        if ($v === null) {
            unset($c->$k);
            return;
        }
        if (in_array($k, self::COLOR_KEYS, true)) {
            $c->$k = $this->Color($v);
            return;
        }
        if (in_array($k, ['Width', 'Height', 'LocationX', 'LocationY'], true) && !is_numeric($v)) {
            throw new Exception('"' . $k . '" muss eine Zahl sein.');
        }
        if ($k === 'ID') {
            $v = (int) $v;
            if ($v > 0 && !IPS_ObjectExists($v)) {
                throw new Exception('Objekt #' . $v . ' gibt es nicht.');
            }
        }
        if ($k === 'Image1' || $k === 'Image2') {
            $v = $this->ImageRef($v);
        }
        if ($k === 'Associations' && is_array($v)) {
            foreach ($v as $i => $a) {
                if (is_array($a) && isset($a['Image'])) {
                    $v[$i]['Image'] = $this->ImageRef($a['Image']);
                } elseif (is_object($a) && isset($a->Image)) {
                    $a->Image = $this->ImageRef($a->Image);
                }
            }
        }
        if ($k === 'ItemLayer' && !in_array((int) $v, [1, 2, 3, 4], true)) {
            throw new Exception('ItemLayer muss 1 (Hintergrund), 2 (Mitte), 3 (Vordergrund) oder 4 (Overlay) sein.');
        }
        if ($k === 'Widget') {
            $c->Widget = $this->NormalizeWidget($v);
            return;
        }
        $c->$k = $this->ToJsonValue($v);
    }

    // Darstellungsart "Text" (Step 100) gibt es nur bei IPSFlowText; bei Wertanzeigen bliebe das Feld leer.
    private function CheckVisuType(object $c): void
    {
        if (in_array($c->Type ?? '', ['IPSVarLabel', 'IPSMarquee'], true) && (int) ($c->Step ?? 0) === 100) {
            throw new Exception('Element ' . ($c->ItemID ?? 'neu') . ': Darstellungsart 100 (Text) gibt es nur bei IPSFlowText. '
                . 'Für Wertanzeigen Step weglassen (0 = Wert) oder 6 (Symcon-Formatierung) verwenden.');
        }
    }

    // Bildverweis: Zahl (Bildkennung oder internes Icon) oder "icon:Name" bzw. "icon:3008"
    private function ImageRef($v): int
    {
        if (is_string($v) && stripos($v, 'icon:') === 0) {
            $name = trim(substr($v, 5));
            if (is_numeric($name)) {
                $v = (int) $name;
            } else {
                $found = array_search(mb_strtolower($name), array_map('mb_strtolower', self::INTERNAL_ICONS), true);
                if ($found === false) {
                    throw new Exception('Internes Icon "' . $name . '" unbekannt. Liste mit {"cmd":"icons"}.');
                }
                return (int) $found;
            }
        }
        $v = (int) $v;
        if ($v > 0 && $v < self::ICON_LIMIT && !isset(self::INTERNAL_ICONS[$v])) {
            throw new Exception('Internes Icon ' . $v . ' gibt es nicht. Liste mit {"cmd":"icons"}.');
        }
        return $v;
    }

    // Ersetzt Texte der Form "ref:Name" durch die Bildkennung bzw. ItemID, die eine frühere Operation derselben
    // Anweisung unter diesem Namen geliefert hat (addImage/addControls mit "ref").
    private function ResolveRefs($v, array $refs)
    {
        if (is_array($v)) {
            foreach ($v as $k => $item) {
                $v[$k] = $this->ResolveRefs($item, $refs);
            }
            return $v;
        }
        if (is_string($v) && preg_match('/^ref:([A-Za-z0-9_.\-]+)$/', $v, $m)) {
            if (!array_key_exists($m[1], $refs)) {
                throw new Exception('Verweis "' . $v . '" unbekannt. Verweise gelten nur für addImage/addControls mit "ref" '
                    . 'weiter vorne in derselben Anweisung.');
            }
            return $refs[$m[1]];
        }
        return $v;
    }

    // Kopie eines Elements mit neuer ItemID; IDs und Texte nach replaceIds/replaceText austauschen
    private function CopyControl(object $view, object $c, array $op): object
    {
        $copy = json_decode(json_encode($c));
        $ids = [];
        foreach ((array) ($op['replaceIds'] ?? []) as $old => $newID) {
            $ids[(int) $old] = (int) $newID;
        }
        $map = function ($v) use ($ids) {
            return $ids[(int) $v] ?? $v;
        };
        if (isset($copy->ID)) {
            $copy->ID = (int) $map($copy->ID);
        }
        foreach (['OnDownID', 'OnPressedID', 'OnReleasedID'] as $k) {
            if (isset($copy->$k)) {
                $copy->$k = (int) $map($copy->$k);
            }
        }
        if (isset($copy->Widget) && is_object($copy->Widget)) {
            foreach (['objectid', 'id2'] as $k) {
                if (isset($copy->Widget->$k) && is_numeric($copy->Widget->$k)) {
                    $copy->Widget->$k = (string) $map($copy->Widget->$k);
                }
            }
            if (isset($copy->Widget->longpressparam)) {
                $copy->Widget->longpressparam = $this->ReplaceTexts((string) $copy->Widget->longpressparam, $op);
            }
        }
        if (isset($copy->WidgetVars) && is_object($copy->WidgetVars) && isset($copy->WidgetVars->WidgetID)) {
            $copy->WidgetVars->WidgetID = (int) $map($copy->WidgetVars->WidgetID);
        }
        if (isset($copy->Remote) && is_object($copy->Remote)) {
            foreach ($copy->Remote as $k => $v) {
                if (strpos($k, 'Variable') === 0 && is_int($v) && $v > 0) {
                    $copy->Remote->$k = (int) $map($v);
                }
            }
        }
        foreach (array_merge(['Text1', 'Text2', 'Prefix', 'Suffix'], self::INLINE_KEYS) as $k) {
            if (isset($copy->$k) && is_string($copy->$k)) {
                $copy->$k = $this->ReplaceTexts($copy->$k, $op);
            }
        }
        if (isset($copy->ID) && $copy->ID > 0 && !IPS_ObjectExists($copy->ID)) {
            throw new Exception('Objekt #' . $copy->ID . ' gibt es nicht.');
        }
        $itemID = (int) ($view->ItemID ?? 1);
        $view->ItemID = $itemID + 1;
        $copy->ItemID = $itemID;
        $copy->ItemName = preg_replace('/^IPS/', '', $copy->Type ?? 'Item') . $itemID;
        unset($copy->IsSelected);
        $this->CheckVisuType($copy);
        $this->NormalizeChart($copy);
        return $copy;
    }

    private function ReplaceTexts(string $s, array $op): string
    {
        foreach ((array) ($op['replaceText'] ?? []) as $old => $new) {
            if ($old !== '') {
                $s = str_replace((string) $old, (string) $new, $s);
            }
        }
        return $s;
    }

    // Diagramme brauchen Widget.objectid; fehlt sie, zeigt IPSView nur den Platzhalter "Chart".
    // Komfort: ohne objectid wird die ID des Elements übernommen.
    private function NormalizeChart(object $c): void
    {
        if (!in_array($c->Type ?? '', ['IPSWidgetIPSViewChart', 'IPSWidgetMediaChart', 'IPSWidgetPieChart'], true)) {
            return;
        }
        $id = (int) ($c->ID ?? 0);
        if (!isset($c->Widget) || !is_object($c->Widget)) {
            $c->Widget = new stdClass();
        }
        $oid = (string) ($c->Widget->objectid ?? '');
        if (($oid === '' || $oid === '0') && $id > 0) {
            $c->Widget->objectid = (string) $id;
        }
    }

    // Stand der Ansicht nach dem Speichern durch das Modul merken
    private function RememberState(int $viewID): void
    {
        try {
            $states = json_decode($this->ReadAttributeString('SavedStates'), true) ?: [];
            $view = json_decode(base64_decode(IPS_GetMediaContent($viewID)));
            $states[(string) $viewID] = ['hash' => md5(IPS_GetMediaContent($viewID)), 'itemID' => (int) ($view->ItemID ?? 0), 'time' => time()];
            $this->WriteAttributeString('SavedStates', json_encode($states));
        } catch (Throwable $e) {
            // Merken ist nur eine Hilfe; Fehler hier dürfen das Speichern nicht verhindern
        }
    }

    // Wurde die Ansicht seit dem letzten Speichern durch das Modul anderweitig geändert (z. B. im Designer)?
    private function ExternalChange(int $viewID, object $view): ?string
    {
        try {
            $states = json_decode($this->ReadAttributeString('SavedStates'), true) ?: [];
        } catch (Throwable $e) {
            return null;
        }
        $s = $states[(string) $viewID] ?? null;
        if ($s === null || $s['hash'] === md5(IPS_GetMediaContent($viewID))) {
            return null;
        }
        $msg = 'Die Ansicht wurde seit dem letzten Speichern durch dieses Modul (' . date('d.m.Y H:i', $s['time'])
            . ') außerhalb geändert, z. B. im IPSView Designer.';
        if ((int) ($view->ItemID ?? 0) < (int) $s['itemID']) {
            $msg .= ' ACHTUNG: Der Elementzähler ist kleiner als damals. Vermutlich wurde ein älterer Stand zurückgeschrieben '
                . 'und Änderungen sind verloren. Mit dem Nutzer klären und ggf. {"cmd":"backups"} / {"cmd":"restore"} nutzen.';
        } else {
            $msg .= ' Mit dem Nutzer klären, ob das gewollt war.';
        }
        return $msg;
    }

    // Hinweise, die das Speichern nicht verhindern
    private function Hints(object $view): array
    {
        $hints = [];
        foreach ($view->Pages as $p) {
            foreach ($p->Controls ?? [] as $c) {
                if (in_array($c->Type ?? '', ['IPSWidgetIPSViewChart', 'IPSWidgetMediaChart', 'IPSWidgetPieChart'], true)) {
                    $oid = (string) ($c->Widget->objectid ?? '');
                    if ($oid === '' || $oid === '0') {
                        $hints[] = $p->PageName . '/' . ($c->ItemID ?? '?') . ': Diagramm ohne Widget.objectid (zeigt nur den Platzhalter „Chart“)';
                    }
                }
            }
        }
        return $hints;
    }

    // Widget-Konfiguration: Schlüssel klein, Werte als Text
    private function NormalizeWidget($v): object
    {
        if (is_object($v)) {
            $v = (array) $v;
        }
        if (!is_array($v)) {
            throw new Exception('"Widget" muss ein Objekt sein.');
        }
        $out = new stdClass();
        foreach ($v as $key => $val) {
            $key = mb_strtolower((string) $key);
            if (is_bool($val)) {
                $val = $val ? 'true' : 'false';
            } elseif (is_int($val) || is_float($val)) {
                $val = (string) $val;
            } elseif ($val === null) {
                continue;
            } elseif (!is_string($val)) {
                throw new Exception('Widget-Wert "' . $key . '" muss Text, Zahl oder Wahrheitswert sein.');
            }
            $out->$key = $val;
        }
        return $out;
    }

    // Position verschieben; bei Demo-Lizenzen sind Positionen verschlüsselt und nicht änderbar
    private function Shift(object $c, float $dx, float $dy): void
    {
        if (!is_numeric($c->LocationX ?? 0) || !is_numeric($c->LocationY ?? 0)) {
            throw new Exception('Element ' . ($c->ItemID ?? '?') . ': Position ist verschlüsselt (Demo-Lizenz) und kann nicht geändert werden.');
        }
        $c->LocationX = (float) ($c->LocationX ?? 0) + $dx;
        $c->LocationY = (float) ($c->LocationY ?? 0) + $dy;
    }

    private function SetPageValue(object $page, string $k, $v): void
    {
        if (in_array($k, ['Controls', 'PageName', 'IsMainPage'], true)) {
            throw new Exception('"' . $k . '" kann hier nicht gesetzt werden.');
        }
        if (!array_key_exists($k, self::PAGE_DEFAULTS) && !in_array($k, self::PAGE_OPTIONAL_KEYS, true) && !property_exists($page, $k)) {
            throw new Exception('Unbekannte Seiten-Eigenschaft "' . $k . '".');
        }
        if ($k === 'BackgroundLayout' && !in_array($v, self::BACKGROUND_LAYOUTS, true)) {
            throw new Exception('Unbekanntes Hintergrund-Layout "' . $v . '".');
        }
        if ($k === 'GridCellWidth') {
            $v = (string) $v; // auf Seitenebene ein Text, z. B. "150" oder "50%"
        }
        if ($k === 'PageImage' || $k === 'BackgroundImage') {
            $v = $this->ImageRef($v);
        }
        $page->$k = (is_string($v) && isset($v[0]) && $v[0] === '#') ? $this->Color($v) : $this->ToJsonValue($v);
    }

    // ---------------------------------------------------------------------------------------------
    // Bilder

    private function AddImage(object $view, array $op): int
    {
        if (isset($op['media'])) {
            $mediaID = (int) $op['media'];
            if (!IPS_MediaExists($mediaID) || IPS_GetMedia($mediaID)['MediaType'] !== 1) {
                throw new Exception('#' . $mediaID . ' ist kein Bild-Medienobjekt.');
            }
            $data = IPS_GetMediaContent($mediaID);
        } elseif (isset($op['file'])) {
            $base = realpath(IPS_GetKernelDir());
            $path = realpath($base . DIRECTORY_SEPARATOR . ltrim((string) $op['file'], '/\\'));
            if ($path === false || strpos($path, $base) !== 0 || !is_file($path)) {
                throw new Exception('Datei nicht gefunden (Pfad relativ zum Symcon-Ordner): ' . $op['file']);
            }
            $data = base64_encode(file_get_contents($path));
        } elseif (isset($op['data'])) {
            $data = preg_replace('/^data:image\/[a-z+]+;base64,/i', '', trim((string) $op['data']));
            $data = preg_replace('/\s+/', '', $data);
            $raw = base64_decode($data, true);
            if ($raw === false) {
                throw new Exception('Bilddaten sind kein gültiges Base64.');
            }
            if (strlen($raw) > 1048576) {
                throw new Exception('Bild zu groß (höchstens 1 MB).');
            }
            $data = base64_encode($raw);
        } else {
            throw new Exception('Bildquelle fehlt (media, file oder data).');
        }
        $info = @getimagesizefromstring(base64_decode($data));
        if ($info === false) {
            throw new Exception('Die Bilddaten lassen sich nicht lesen.');
        }
        $hash = $this->DartStringHash($data);
        // Kennungen unter 1.000.000 hält der Client für interne Icons. In dem seltenen Fall wird das Bild
        // um einen unsichtbaren Zusatz ergänzt, bis die Kennung passt.
        for ($n = 1; $hash < self::ICON_LIMIT; $n++) {
            $raw = base64_decode($data);
            if (substr($raw, 0, 8) === "\x89PNG\r\n\x1a\n") {
                $text = 'Comment' . "\0" . 'IVT' . $n;
                $chunk = pack('N', strlen($text)) . 'tEXt' . $text . pack('N', crc32('tEXt' . $text));
                $raw = substr($raw, 0, 8) . substr($raw, 8, 25) . $chunk . substr($raw, 33);
            } else {
                $raw .= "\0";
            }
            $data = base64_encode($raw);
            $hash = $this->DartStringHash($data);
            if ($n > 50) {
                throw new Exception('Für das Bild lässt sich keine gültige Kennung bilden.');
            }
        }
        foreach ($view->Images ?? [] as $img) {
            if ((int) $img->ImageHash === $hash) {
                return $hash;
            }
        }
        $view->Images[] = (object) [
            'ImageData' => $data, 'ImageHash' => $hash, 'ImageType' => -1,
            'Height' => (int) $info[1], 'Width' => (int) $info[0]
        ];
        return $hash;
    }

    // Gleicher Hashwert wie String.hashCode im IPSView-Designer (Kennung von Bildern)
    private function DartStringHash(string $s): int
    {
        $h = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $h = ($h + ord($s[$i])) & 0x1fffffff;
            $h = ($h + (($h & 0x0007ffff) << 10)) & 0x1fffffff;
            $h ^= $h >> 6;
        }
        $h = ($h + (($h & 0x03ffffff) << 3)) & 0x1fffffff;
        $h ^= $h >> 11;
        return ($h + (($h & 0x00003fff) << 15)) & 0x1fffffff;
    }

    // ---------------------------------------------------------------------------------------------
    // Prüfung

    private function Validate(object $view): array
    {
        $problems = [];
        $pageNames = [];
        foreach ($view->Pages as $p) {
            if (isset($pageNames[$p->PageName])) {
                $problems[] = 'Seite "' . $p->PageName . '" doppelt';
            }
            $pageNames[$p->PageName] = true;
        }
        $hashes = [0 => true, -1 => true];
        foreach ($view->Images ?? [] as $img) {
            $hashes[(int) $img->ImageHash] = true;
        }
        $itemIDs = [];
        $mainCount = 0;
        $imageOk = function ($v) use ($hashes) {
            $v = (int) $v;
            return isset($hashes[$v]) || ($v > 0 && $v < self::ICON_LIMIT); // interne Icons
        };
        foreach ($view->Pages as $p) {
            $kind = $this->PageKind($p);
            if ($kind === 'main') {
                $mainCount++;
            }
            if (in_array($kind, ['standard', 'popup', 'alternateMain'], true)
                && ((float) ($p->PopupWidth ?? 0) <= 0 || (float) ($p->PopupHeight ?? 0) <= 0)) {
                $problems[] = 'Seite "' . $p->PageName . '": Größe fehlt (PopupWidth/PopupHeight)';
            }
            $resize = (int) ($p->ResizeOption ?? 0);
            if (isset(self::RESIZE_OPTIONS[$kind]) && !in_array($resize, self::RESIZE_OPTIONS[$kind], true)) {
                $problems[] = 'Seite "' . $p->PageName . '": Größenanpassung ' . $resize . ' passt nicht zur Seitenart ' . $kind
                    . ' (möglich: ' . implode(', ', self::RESIZE_OPTIONS[$kind]) . ')';
            }
            if ($kind === 'alternateMain' && ($p->ConditionWidth ?? '') === '' && ($p->ConditionHeight ?? '') === ''
                && ($p->ConditionConfig ?? '') === '') {
                $problems[] = 'Seite "' . $p->PageName . '": alternative Hauptseite braucht ConditionWidth, ConditionHeight oder ConditionConfig';
            }
            foreach (['BackgroundImage', 'PageImage'] as $k) {
                if (!empty($p->$k) && !$imageOk($p->$k)) {
                    $problems[] = 'Seite "' . $p->PageName . '": Bild ' . $p->$k . ' (' . $k . ') fehlt';
                }
            }
            foreach ($p->Controls ?? [] as $c) {
                $where = $p->PageName . '/' . ($c->ItemID ?? '?');
                if (!in_array($c->Type ?? '', self::CONTROL_TYPES, true) && !in_array($c->Type ?? '', self::LEGACY_CONTROL_TYPES, true)) {
                    $problems[] = $where . ': unbekannter Typ ' . ($c->Type ?? '');
                }
                foreach ($c as $k => $v) {
                    if (!in_array($k, self::CONTROL_KEYS, true)) {
                        $problems[] = $where . ': unbekannte Eigenschaft ' . $k;
                    }
                }
                $iid = $c->ItemID ?? null;
                if ($iid !== null) {
                    if (isset($itemIDs[$iid])) {
                        $problems[] = $where . ': ItemID doppelt';
                    }
                    $itemIDs[$iid] = true;
                    if ($iid >= (int) ($view->ItemID ?? 0)) {
                        $view->ItemID = $iid + 1;
                    }
                }
                $id = (int) ($c->ID ?? 0);
                if ($id > 0 && !IPS_ObjectExists($id)) {
                    $problems[] = $where . ': Objekt #' . $id . ' fehlt';
                }
                foreach (self::INLINE_KEYS as $k) {
                    if (!empty($c->$k) && !isset($pageNames[$c->$k])) {
                        $problems[] = $where . ': Zielseite "' . $c->$k . '" fehlt';
                    }
                }
                if (isset($c->Widget) && is_object($c->Widget) && (string) ($c->Widget->longpressaction ?? '') === '20'
                    && !isset($pageNames[(string) ($c->Widget->longpressparam ?? '')])) {
                    $problems[] = $where . ': Popup-Seite "' . ($c->Widget->longpressparam ?? '') . '" für langes Drücken fehlt';
                }
                if (in_array($c->Type ?? '', ['IPSDetailButton', 'IPSDetailImage'], true)) {
                    $target = (string) ($c->Text2 ?? '');
                    if ($target !== '' && $target[0] !== '$' && !isset($pageNames[$target])) {
                        $problems[] = $where . ': Popup-Seite "' . $target . '" fehlt';
                    }
                }
                if (($c->Type ?? '') === 'IPSInlinePage') {
                    if (!empty($c->Text1) && !isset($pageNames[$c->Text1])) {
                        $problems[] = $where . ': Startseite "' . $c->Text1 . '" fehlt';
                    }
                    if (!isset(self::CONTAINER_INLINE_KEY[(int) ($c->Digits ?? 5)])) {
                        $problems[] = $where . ': ungültiger Containertyp';
                    }
                }
                foreach (['Image1', 'Image2'] as $k) {
                    if (isset($c->$k) && (int) $c->$k > 0 && !$imageOk($c->$k)) {
                        $problems[] = $where . ': Bild ' . $c->$k . ' fehlt';
                    }
                }
                if (isset($c->Associations) && is_array($c->Associations)) {
                    foreach ($c->Associations as $a) {
                        $img = (int) ($a->Image ?? 0);
                        if ($img > 0 && !$imageOk($img)) {
                            $problems[] = $where . ': Bild ' . $img . ' fehlt (Zuordnung "' . ($a->Text ?? ($a->Value ?? '?')) . '")';
                        }
                    }
                }
            }
        }
        if ($mainCount !== 1) {
            $problems[] = 'Es muss genau eine Hauptseite geben (gefunden: ' . $mainCount . ')';
        }
        return $problems;
    }

    // ---------------------------------------------------------------------------------------------
    // Hilfsfunktionen

    private function LoadView(int $viewID): object
    {
        if (!IPS_MediaExists($viewID) || IPS_GetMedia($viewID)['MediaType'] !== 0) {
            throw new Exception('#' . $viewID . ' ist keine IPSView-Ansicht.');
        }
        $view = json_decode(base64_decode(IPS_GetMediaContent($viewID)));
        if (!is_object($view) || !isset($view->Pages) || !is_array($view->Pages)) {
            throw new Exception('Ansicht #' . $viewID . ' lässt sich nicht lesen.');
        }
        foreach ($view->Pages as $p) {
            if (!isset($p->Controls) || !is_array($p->Controls)) {
                $p->Controls = [];
            }
        }
        if (!isset($view->Images) || !is_array($view->Images)) {
            $view->Images = [];
        }
        return $view;
    }

    private function SaveView(int $viewID, object $view): void
    {
        $view->ID = $viewID;
        $json = json_encode($view, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Exception('Ansicht kann nicht gespeichert werden: ' . json_last_error_msg());
        }
        IPS_SetMediaContent($viewID, base64_encode($json));
    }

    private function FindPage(object $view, string $name, bool $required = true): ?object
    {
        foreach ($view->Pages as $p) {
            if ($p->PageName === $name) {
                return $p;
            }
        }
        if ($required) {
            $names = array_map(function ($p) {
                return $p->PageName;
            }, $view->Pages);
            throw new Exception('Seite "' . $name . '" nicht gefunden. Vorhanden: ' . implode(', ', $names));
        }
        return null;
    }

    private function FindControl(object $page, int $itemID): object
    {
        foreach ($page->Controls as $c) {
            if ((int) ($c->ItemID ?? -1) === $itemID) {
                return $c;
            }
        }
        throw new Exception('Element ' . $itemID . ' auf Seite "' . $page->PageName . '" nicht gefunden.');
    }

    private function ResolveItems(object $page, $items): array
    {
        if ($items === 'all') {
            return array_map(function ($c) {
                return $c->ItemID ?? null;
            }, $page->Controls);
        }
        if (!is_array($items) || !$items) {
            throw new Exception('Keine Elemente angegeben (items).');
        }
        $ids = array_map('intval', $items);
        foreach ($ids as $id) {
            $this->FindControl($page, $id);
        }
        return $ids;
    }

    private function Reindex(object $view): void
    {
        foreach ($view->Pages as $idx => $p) {
            foreach ($p->Controls as $i => $c) {
                $c->ItemIdx = $i;
            }
        }
    }

    private function UpdateUsedIDs(object $view): void
    {
        $used = (isset($view->UsedIDs) && is_object($view->UsedIDs)) ? $view->UsedIDs : new stdClass();
        foreach ($view->Pages as $p) {
            foreach ($p->Controls as $c) {
                $id = (int) ($c->ID ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $key = (string) $id;
                $writes = in_array($c->Type ?? '', self::WRITING_TYPES, true);
                $used->$key = (isset($used->$key) && $used->$key) || $writes;
            }
        }
        $view->UsedIDs = $used;
    }

    private function ReplacePageReferences(object $view, string $old, string $new): int
    {
        $n = 0;
        foreach ($view->Pages as $p) {
            foreach (['GestureWipeLeftParam', 'GestureWipeRightParam', 'GestureWipeUpParam', 'GestureWipeDownParam'] as $k) {
                if (($p->$k ?? '') === $old) {
                    $p->$k = $new;
                    $n++;
                }
            }
            foreach ($p->Controls as $c) {
                foreach (self::INLINE_KEYS as $k) {
                    if (($c->$k ?? '') === $old) {
                        $c->$k = $new;
                        $n++;
                    }
                }
                if (($c->Type ?? '') === 'IPSInlinePage' && ($c->Text1 ?? '') === $old) {
                    $c->Text1 = $new;
                    $n++;
                }
            }
        }
        if (($view->CurrentPageName ?? '') === $old) {
            $view->CurrentPageName = $new;
        }
        return $n;
    }

    // Seitenart nach der Rangfolge, mit der IPSView die Typ-Kennzeichen auswertet. Die Hauptseite sucht
    // IPSView direkt über IsMainPage, deshalb wird sie hier zuerst erkannt.
    private function PageKind(object $p): string
    {
        $order = [
            'IsMainPage' => 'main', 'IsFolder' => 'folder', 'IsAlternateMain' => 'alternateMain', 'IsAlternatePage' => 'alternate',
            'IsInline' => 'standard', 'IsPopup' => 'popup',
            'IsCustomControl' => 'customControl', 'IsCustomFolder' => 'customFolder'
        ];
        foreach ($order as $flag => $kind) {
            if (!empty($p->$flag)) {
                return $kind;
            }
        }
        return 'tab';
    }

    private function ContainerTypeValue($v): int
    {
        if (is_int($v) && isset(self::CONTAINER_INLINE_KEY[$v])) {
            return $v;
        }
        $key = strtolower((string) $v);
        if (!isset(self::CONTAINER_TYPES[$key])) {
            throw new Exception('Unbekannter Containertyp "' . $v . '". Möglich: ' . implode(', ', array_keys(self::CONTAINER_TYPES)));
        }
        return self::CONTAINER_TYPES[$key];
    }

    private function Color($v): object
    {
        if (is_object($v)) {
            $v = (array) $v;
        }
        if (is_array($v)) {
            foreach (['R', 'G', 'B'] as $k) {
                if (!isset($v[$k])) {
                    throw new Exception('Farbe ohne ' . $k);
                }
            }
            return (object) array_merge(['A' => 255, 'Type' => 0, 'Pattern' => '12'], $v);
        }
        $hex = ltrim((string) $v, '#');
        if (!preg_match('/^([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex)) {
            throw new Exception('Ungültige Farbe "' . $v . '" (erwartet #RRGGBB oder #AARRGGBB).');
        }
        $a = 255;
        if (strlen($hex) === 8) {
            $a = hexdec(substr($hex, 0, 2));
            $hex = substr($hex, 2);
        }
        return (object) [
            'A' => $a, 'R' => hexdec(substr($hex, 0, 2)), 'G' => hexdec(substr($hex, 2, 2)), 'B' => hexdec(substr($hex, 4, 2)),
            'Type' => 0, 'Pattern' => '12'
        ];
    }

    // Arrays mit Schlüsseln als Objekte speichern, damit {} nicht zu [] wird
    private function ToJsonValue($v)
    {
        if (is_array($v)) {
            $isList = $v === [] || array_keys($v) === range(0, count($v) - 1);
            if ($isList) {
                return array_map([$this, 'ToJsonValue'], $v);
            }
            $o = new stdClass();
            foreach ($v as $k => $x) {
                $o->$k = $this->ToJsonValue($x);
            }
            return $o;
        }
        return $v;
    }

    private function RequireInt(array $cmd, string $key): int
    {
        if (!isset($cmd[$key]) || !is_numeric($cmd[$key])) {
            throw new Exception('Parameter "' . $key . '" fehlt.');
        }
        return (int) $cmd[$key];
    }

    private function RequireString(array $cmd, string $key): string
    {
        if (!isset($cmd[$key]) || !is_string($cmd[$key]) || $cmd[$key] === '') {
            throw new Exception('Parameter "' . $key . '" fehlt.');
        }
        return $cmd[$key];
    }

    private function RequireArray(array $cmd, string $key): array
    {
        if (!isset($cmd[$key]) || !is_array($cmd[$key])) {
            throw new Exception('Parameter "' . $key . '" fehlt.');
        }
        return $cmd[$key];
    }

    // ---------------------------------------------------------------------------------------------
    // Neue Ansicht, Sicherungen, Zwischenspeicher

    private function CreateViewCmd(string $name, int $templateID): array
    {
        if (!$this->GetValue('AllowCreateView')) {
            throw new Exception('Neue Ansichten werden außerhalb dieser Instanz angelegt und brauchen die Freigabe des Nutzers: '
                . 'Der Nutzer muss die Variable „Neue Ansicht anlegen erlauben“ der Instanz einschalten. '
                . 'Eine KI darf diesen Schalter nicht selbst setzen.');
        }
        $tpl = $this->LoadView($templateID);
        $main = null;
        foreach ($tpl->Pages as $p) {
            if (!empty($p->IsMainPage)) {
                $main = clone $p;
                break;
            }
        }
        if ($main === null) {
            throw new Exception('Die Vorlage hat keine Hauptseite.');
        }
        $main->Controls = [];
        $main->BackgroundImage = 0;
        $main->PageTitle = $name;
        $tpl->Pages = [$main];
        $tpl->Images = [];
        $tpl->UsedIDs = new stdClass();
        $tpl->GroupIDs = new stdClass();
        $tpl->VirtualIDs = [];
        $tpl->LruIDs = [];
        $tpl->ItemID = 1;
        $tpl->Name = $name;
        $tpl->CurrentPageName = $main->PageName;
        $tpl->CurrentCustomName = '';
        $tpl->PageHistory = [];
        $tpl->CustomHistory = [];
        $id = IPS_CreateMedia(0);
        IPS_SetMediaFile($id, 'media/' . $id . '.ipsView', false);
        IPS_SetName($id, $name);
        IPS_SetParent($id, IPS_GetParent($templateID));
        $this->SaveView($id, $tpl);
        $this->RememberState($id);
        $this->SetValue('AllowCreateView', false); // Freigabe gilt nur für eine neue Ansicht
        return ['view' => $id, 'mainPage' => $main->PageName, 'cacheReset' => $this->ResetClientCache()];
    }

    private function CreateBackup(int $viewID, string $content): int
    {
        $id = IPS_CreateMedia(5);
        IPS_SetParent($id, $this->InstanceID);
        IPS_SetMediaFile($id, 'media/ivt_backup_' . $viewID . '_' . date('Ymd_His') . '_' . $id . '.ipsView.bak', false);
        IPS_SetName($id, 'Sicherung ' . IPS_GetName($viewID) . ' (#' . $viewID . ') ' . date('d.m.Y H:i:s'));
        IPS_SetIdent($id, 'Backup_' . $viewID . '_' . $id);
        IPS_SetInfo($id, (string) $viewID);
        IPS_SetHidden($id, true);
        IPS_SetMediaContent($id, $content);
        $this->PruneBackups($viewID);
        return $id;
    }

    private function BackupIDs(int $viewID): array
    {
        $ids = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $child) {
            $o = IPS_GetObject($child);
            if ($o['ObjectType'] === 5 && strpos($o['ObjectIdent'], 'Backup_' . $viewID . '_') === 0) {
                $ids[] = $child;
            }
        }
        sort($ids);
        return $ids;
    }

    private function PruneBackups(int $viewID): void
    {
        $ids = $this->BackupIDs($viewID);
        $keep = max(1, $this->ReadPropertyInteger('BackupKeep'));
        while (count($ids) > $keep) {
            IPS_DeleteMedia(array_shift($ids), true);
        }
    }

    private function ListBackups(int $viewID): array
    {
        $out = [];
        foreach ($this->BackupIDs($viewID) as $id) {
            $out[] = ['backup' => $id, 'name' => IPS_GetName($id), 'size' => IPS_GetMedia($id)['MediaSize']];
        }
        return $out;
    }

    private function Restore(int $viewID, int $backupID): array
    {
        if (!in_array($backupID, $this->BackupIDs($viewID), true)) {
            throw new Exception('Sicherung #' . $backupID . ' gehört nicht zu Ansicht #' . $viewID . '.');
        }
        $this->LoadView($viewID);
        $current = IPS_GetMediaContent($viewID);
        $content = IPS_GetMediaContent($backupID);
        $check = json_decode(base64_decode($content));
        if (!is_object($check) || !isset($check->Pages)) {
            throw new Exception('Sicherung #' . $backupID . ' ist beschädigt.');
        }
        $safety = $this->CreateBackup($viewID, $current);
        IPS_SetMediaContent($viewID, $content);
        $this->RememberState($viewID);
        return ['restored' => $backupID, 'backupOfPrevious' => $safety, 'cacheReset' => $this->ResetClientCache()];
    }

    private function ResetClientCache(): int
    {
        $n = 0;
        if (function_exists('IVC_ResetCache')) {
            foreach (IPS_GetInstanceListByModuleID(self::IVC_MODULE_ID) as $id) {
                @IVC_ResetCache($id);
                $n++;
            }
        }
        return $n;
    }
}
