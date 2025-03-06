<?php
/**
 * ChartConfig - Konfigurationsklasse für das PHPChart-System
 * 
 * Diese Klasse verwaltet alle Konfigurationsoptionen und Standardwerte
 * für das Diagramm, Achsen, Serien und andere Elemente.
 * 
 * @version 1.2
 */
class ChartConfig {
    /**
     * Standardkonfiguration für das Diagramm
     * 
     * @var array
     */
    private $defaultConfig = [
        'width' => 800,             // Breite des SVG in Pixeln
        'height' => 500,            // Höhe des SVG in Pixeln
        'margin' => [              // Ränder des Diagramms
            'top' => 50,
            'right' => 50,
            'bottom' => 50,
            'left' => 50
        ],
        'background' => [           // Hintergrundeinstellungen
            'enabled' => true,
            'color' => '#ffffff',
            'borderRadius' => 0
        ],
        'grid' => [                 // Gittereinstellungen
            'enabled' => true,
            'color' => '#e0e0e0',
            'width' => 1,
            'dashArray' => ''
        ],
        'title' => [                // Titeleinstellungen
            'text' => '',
            'enabled' => false,
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 18,
            'fontWeight' => 'bold',
            'color' => '#333333',
            'align' => 'center',
            'offsetX' => 0,
            'offsetY' => 20
        ],
        'legend' => [               // Standardeinstellungen für die Legende
            'enabled' => true,
            'position' => 'bottom', // bottom, top, left, right, custom
            'align' => 'center',    // left, center, right
            'x' => 0,               // Bei position=custom
            'y' => 0,               // Bei position=custom
            'layout' => 'horizontal', // horizontal, vertical
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 12,
            'fontWeight' => 'normal',
            'color' => '#333333',
            'symbolSize' => 10,
            'symbolSpacing' => 5,
            'itemSpacing' => 20,
            'padding' => 10,
            'background' => '#ffffff',
            'borderRadius' => 0,
            'border' => [
                'enabled' => false,
                'color' => '#cccccc',
                'width' => 1
            ]
        ]
    ];
    
    /**
     * Standardeinstellungen für Datenreihen
     * 
     * @var array
     */
    private $defaultSeriesOptions = [
        'type' => 'bar',            // Chart-Typ: bar, line, spline, area, pie, etc.
        'xAxisId' => 0,             // ID der zu verwendenden X-Achse
        'yAxisId' => 0,             // ID der zu verwendenden Y-Achse
        'color' => '',              // Farbe (automatisch, wenn leer)
        'opacity' => 1,             // Deckkraft
        'fillOpacity' => 0.8,       // Deckkraft der Füllung
        'gradient' => [             // Gradient-Einstellungen
            'enabled' => false,
            'colors' => [],         // Array mit Farben für den Gradienten
            'stops' => [],          // Array mit Stop-Positionen (optional, in %)
            'type' => 'linear',     // linear, radial
            'angle' => 90           // 0-360 Grad, nur für linear
        ],
        'stacked' => false,         // Gestapelte Darstellung
        'stackGroup' => 'default',  // Stapelgruppe, falls stacked=true
        'showInLegend' => true,     // In Legende anzeigen
        'legendText' => '',         // Benutzerdefinierter Legendentext
        
        // Punktoptionen
        'point' => [
            'enabled' => false,     // Punkte anzeigen (automatisch für line/scatter)
            'size' => 5,            // Punktgröße
            'shape' => 'circle',    // circle, square, triangle, diamond
            'color' => '',          // Punktfarbe (Serie, wenn leer)
            'borderColor' => '',    // Rahmenfarbe (schwarz, wenn leer)
            'borderWidth' => 1      // Rahmenbreite
        ],
        
        // Beschriftungsoptionen
        'dataLabels' => [
            'enabled' => false,     // Datenwertbeschriftung anzeigen
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 11,
            'fontWeight' => 'normal',
            'color' => '#333333',
            'format' => '{y}',      // Format: {y}, {x}, {percentage}
            'offsetX' => 0,
            'offsetY' => -15,
            'rotation' => 0         // 0-360 Grad
        ],
        
        // Spezifische Optionen für Balkendiagramme
        'bar' => [
            'width' => null,        // Breite (automatisch, wenn null)
            'maxWidth' => 50,       // Maximale Breite
            'cornerRadius' => 0,    // Eckenradius
            'columnSpacing' => 0.2, // Abstand als Anteil der Kategoriebreite
            'horizontal' => false   // Horizontaler Balken (Bar Chart)
        ],
        
        // Spezifische Optionen für Liniendiagramme
        'line' => [
            'width' => 2,           // Linienbreite
            'dashArray' => '',      // Strichmuster (z.B. "5,5" für gestrichelt)
            'stepped' => false,     // Treppenlinie
            'connectNulls' => false // Verbinde über null-Werte hinweg
        ],
        
        // Spezifische Optionen für Flächendiagramme
        'area' => [
            'strokeWidth' => 2,     // Linienbreite
            'fillOpacity' => 0.4    // Deckkraft der Füllung
        ],
        
        // Spezifische Optionen für Kreisdiagramme
        'pie' => [
            'innerRadius' => 0,     // Innerer Radius (0 für Pie, >0 für Donut)
            'startAngle' => 0,      // Startwinkel in Grad
            'endAngle' => 360,      // Endwinkel in Grad
            'padAngle' => 0,        // Abstandswinkel zwischen Segmenten
            'cornerRadius' => 0     // Eckenradius der Segmente
        ],
        
        // Spezifische Optionen für Bubble-Diagramme
        'bubble' => [
            'minSize' => 5,         // Minimale Blasengröße
            'maxSize' => 50,        // Maximale Blasengröße
            'sizeField' => 'size'   // Name des Feldes für die Größe
        ]
    ];
    
    /**
     * Standardeinstellungen für X-Achsen
     * 
     * @var array
     */
    private $defaultXAxisOptions = [
        'enabled' => true,
        'position' => 'bottom',     // bottom, top
        'title' => [
            'text' => '',
            'enabled' => false,
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 14,
            'fontWeight' => 'bold',
            'color' => '#333333',
            'offsetX' => 0,
            'offsetY' => 35
        ],
        'type' => 'category',       // category, numeric, time, log, string
        'categories' => [],         // Kategorien für category-Typ
        'dateFormat' => 'd/m/Y',    // Datumsformat für time-Typ
        'min' => null,              // Minimalwert (automatisch, wenn null)
        'max' => null,              // Maximalwert (automatisch, wenn null)
        'tickAmount' => null,       // Anzahl der Ticks (automatisch, wenn null)
        'tickInterval' => null,     // Intervall zwischen Ticks
        'labels' => [
            'enabled' => true,      // Wichtig: Standardmäßig aktiviert
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 12,
            'fontWeight' => 'normal',
            'color' => '#333333',
            'rotation' => 0,        // Rotation in Grad
            'align' => 'center',    // left, center, right
            'format' => null,       // Benutzerdefiniertes Format
            'overflow' => 'truncate' // truncate, wrap, hide
        ],
        'line' => [
            'enabled' => true,      // Wichtig: Standardmäßig aktiviert
            'color' => '#999999',
            'width' => 1,
            'dashArray' => ''
        ],
        'ticks' => [
            'enabled' => true,      // Wichtig: Standardmäßig aktiviert
            'color' => '#999999',
            'width' => 1,
            'size' => 6
        ],
        'grid' => [
            'enabled' => true,
            'color' => '#e0e0e0',
            'width' => 1,
            'dashArray' => ''
        ]
    ];
    
    /**
     * Standardeinstellungen für Y-Achsen
     * 
     * @var array
     */
    private $defaultYAxisOptions = [
        'enabled' => true,
        'position' => 'left',       // left, right
        'title' => [
            'text' => '',
            'enabled' => false,
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 14,
            'fontWeight' => 'bold',
            'color' => '#333333',
            'offsetX' => -35,
            'offsetY' => 0,
            'rotation' => -90       // Rotation in Grad
        ],
        'type' => 'numeric',        // numeric, log
        'min' => null,              // Minimalwert (automatisch, wenn null)
        'max' => null,              // Maximalwert (automatisch, wenn null)
        'tickAmount' => null,       // Anzahl der Ticks (automatisch, wenn null)
        'tickInterval' => null,     // Intervall zwischen Ticks
        'labels' => [
            'enabled' => true,      // Wichtig: Standardmäßig aktiviert
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 12,
            'fontWeight' => 'normal',
            'color' => '#333333',
            'align' => 'right',     // left, center, right
            'format' => null,       // Benutzerdefiniertes Format
            'prefix' => '',         // Präfix
            'suffix' => '',         // Suffix
            'decimals' => 0         // Anzahl der Dezimalstellen (0 für ganze Zahlen)
        ],
        'line' => [
            'enabled' => true,      // Wichtig: Standardmäßig aktiviert
            'color' => '#999999',
            'width' => 1,
            'dashArray' => ''
        ],
        'ticks' => [
            'enabled' => true,      // Wichtig: Standardmäßig aktiviert
            'color' => '#999999',
            'width' => 1,
            'size' => 6
        ],
        'grid' => [
            'enabled' => true,
            'color' => '#e0e0e0',
            'width' => 1,
            'dashArray' => ''
        ]
    ];
    
    /**
     * Standardfarben für Datenreihen
     * 
     * @var array
     */
    private $defaultColors = [
        '#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', 
        '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92', '#5F9EB7'
    ];
    
    /**
     * Führt die übergebene Konfiguration mit den Standardwerten zusammen
     * 
     * @param array $userConfig Benutzerdefinierte Konfiguration
     * @param array $baseConfig Basiskonfiguration (Standard: $this->defaultConfig)
     * @return array Zusammengeführte Konfiguration
     */
    public function mergeConfig($userConfig = [], $baseConfig = null) {
        if ($baseConfig === null) {
            $baseConfig = $this->defaultConfig;
        }
        
        // Rekursives Zusammenführen von Konfigurationen
        return $this->mergeConfigRecursive($baseConfig, $userConfig);
    }
    
    /**
     * Rekursives Zusammenführen von Konfigurationen
     * 
     * @param array $baseConfig Basiskonfiguration
     * @param array $userConfig Benutzerdefinierte Konfiguration
     * @return array Zusammengeführte Konfiguration
     */
    private function mergeConfigRecursive($baseConfig, $userConfig) {
        $merged = $baseConfig;
        
        if (is_array($userConfig) || is_object($userConfig)) {
            foreach ($userConfig as $key => $value) {
            // Wenn der Wert ein Array ist und der Schlüssel auch im Basis-Array existiert
                if (is_array($value) && isset($baseConfig[$key]) && is_array($baseConfig[$key])) {
                    $merged[$key] = $this->mergeConfigRecursive($baseConfig[$key], $value);
             } else {
                    $merged[$key] = $value;
                }
            }
        }
        return $merged;
    }
    
    /**
     * Gibt die Standardkonfiguration zurück
     * 
     * @return array Standardkonfiguration
     */
    public function getDefaultConfig() {
        return $this->defaultConfig;
    }
    
    /**
     * Gibt die Standardeinstellungen für Datenreihen zurück
     * 
     * @return array Standardeinstellungen für Datenreihen
     */
    public function getDefaultSeriesOptions() {
        return $this->defaultSeriesOptions;
    }
    
    /**
     * Gibt die Standardeinstellungen für X-Achsen zurück
     * 
     * @return array Standardeinstellungen für X-Achsen
     */
    public function getDefaultXAxisOptions() {
        return $this->defaultXAxisOptions;
    }
    
    /**
     * Gibt die Standardeinstellungen für Y-Achsen zurück
     * 
     * @return array Standardeinstellungen für Y-Achsen
     */
    public function getDefaultYAxisOptions() {
        return $this->defaultYAxisOptions;
    }
    
    /**
     * Gibt die Standardeinstellungen für die Legende zurück
     * 
     * @return array Standardeinstellungen für die Legende
     */
    public function getDefaultLegendOptions() {
        return $this->defaultConfig['legend'];
    }
    
    /**
     * Gibt eine Standardfarbe basierend auf dem Index zurück
     * 
     * @param int $index Index der Farbe
     * @return string Farbe im Hex-Format
     */
    public function getDefaultColor($index) {
        return $this->defaultColors[$index % count($this->defaultColors)];
    }
    
    /**
     * Gibt alle Standardfarben zurück
     * 
     * @return array Array mit Standardfarben
     */
    public function getDefaultColors() {
        return $this->defaultColors;
    }
}
?>