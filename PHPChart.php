<?php
/**
 * PHPChart - Eine umfassende PHP-Klasse zur Erstellung von SVG-Diagrammen
 * 
 * Diese Klasse ermöglicht die Erstellung verschiedener Diagrammtypen mit
 * anpassbaren Optionen für Darstellung, Achsen, Legenden und mehr.
 * 
 * @author Claude
 * @version 1.4
 */

// Einbinden der benötigten Klassendateien
include_once('chart.class.config.php');
include_once('chart.class.utils.php');
include_once('chart.class.svg.php');
include_once('chart.class.axes.php');
include_once('chart.class.barchart.php');
include_once('chart.class.linechart.php');
include_once('chart.class.areachart.php');
include_once('chart.class.piechart.php');
include_once('chart.class.polarchart.php');
include_once('chart.class.scatterchart.php');
include_once('chart.class.waterfall.php');
include_once('chart.class.legend.php');

class PHPChart {
    /**
     * @var array Konfigurationsobjekt für das Diagramm
     */
    private $config;
    
    /**
     * @var array Array mit den X-Werten für die Datenreihen
     */
    private $xValues;
    
    /**
     * @var array Array mit den Y-Werten für die Datenreihen
     */
    private $yValues;
    
    /**
     * @var array Array mit den Serien-Definitionen
     */
    private $series;
    
    /**
     * @var array Array mit den Achsen-Definitionen
     */
    private $axes;
    
    /**
     * @var string Die generierte SVG-Ausgabe
     */
    private $svgOutput;
    
    /**
     * @var ChartConfig Instanz der Konfigurationsklasse
     */
    private $chartConfig;
    
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $chartUtils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $chartSVG;
    
    /**
     * @var ChartAxes Instanz der Achsen-Klasse
     */
    private $chartAxes;
    
    /**
     * @var ChartLegend Instanz der Legenden-Klasse
     */
    private $chartLegend;
    
    /**
     * @var bool Flag für horizontale Balkendiagramme
     */
    private $hasHorizontalBars = false;
    
    /**
     * Konstruktor - Initialisiert die Basiskomponenten des Diagramms
     * 
     * @param array $config Optionale Konfigurationsparameter
     */
    public function __construct($config = []) {
        // Initialisieren der Hilfsobjekte
        $this->chartConfig = new ChartConfig();
        $this->chartUtils = new ChartUtils();
        $this->chartSVG = new ChartSVG();
        $this->chartAxes = new ChartAxes();
        $this->chartLegend = new ChartLegend();
        
        // Mergen der übergebenen Konfiguration mit den Standardwerten
        $this->config = $this->chartConfig->mergeConfig($config);
        
        // Initialisieren der Datenstrukturen
        $this->xValues = [];
        $this->yValues = [];
        $this->series = [];
        $this->axes = [
            'x' => [],
            'y' => []
        ];
        $this->svgOutput = '';
    }
    
    /**
     * Fügt eine Serie von X-Werten hinzu
     * 
     * @param array $values Array mit X-Werten
     * @param string $name Optionaler Name der X-Achse
     * @return PHPChart Fluent Interface
     */
    public function addXValues($values, $name = 'default') {
        $this->xValues[$name] = $values;
        return $this;
    }
    
    /**
     * Fügt eine Serie von Y-Werten hinzu
     * 
     * @param array $values Array mit Y-Werten
     * @param string $name Name der Datenreihe
     * @param array $options Optionale Einstellungen für die Datenreihe
     * @return PHPChart Fluent Interface
     */
    public function addYValues($values, $name, $options = []) {
        $this->yValues[$name] = $values;
        
        // Standard-Optionen mit benutzerdefinierten Optionen zusammenführen
        $defaultOptions = $this->chartConfig->getDefaultSeriesOptions();
        $seriesOptions = array_merge($defaultOptions, $options);
        
        // Prüfen, ob es sich um horizontale Balken handelt
        if (isset($seriesOptions['bar']) && isset($seriesOptions['bar']['horizontal']) && $seriesOptions['bar']['horizontal']) {
            $this->hasHorizontalBars = true;
        }
        
        // Serie mit Optionen hinzufügen
        $this->series[$name] = $seriesOptions;
        
        return $this;
    }
    
    /**
     * Fügt eine X-Achse hinzu
     * 
     * @param array $options Einstellungen für die X-Achse
     * @return PHPChart Fluent Interface
     */
    public function addXAxis($options = []) {
        $axisId = count($this->axes['x']);
        $defaultOptions = $this->chartConfig->getDefaultXAxisOptions();
        $this->axes['x'][$axisId] = array_merge($defaultOptions, $options);
        return $this;
    }
    
    /**
     * Fügt eine Y-Achse hinzu
     * 
     * @param array $options Einstellungen für die Y-Achse
     * @return PHPChart Fluent Interface
     */
    public function addYAxis($options = []) {
        $axisId = count($this->axes['y']);
        $defaultOptions = $this->chartConfig->getDefaultYAxisOptions();
        $this->axes['y'][$axisId] = array_merge($defaultOptions, $options);
        return $this;
    }
    
    /**
     * Setzt Optionen für die Legende
     * 
     * @param array $options Legenden-Einstellungen
     * @return PHPChart Fluent Interface
     */
    public function setLegendOptions($options = []) {
        $defaultOptions = $this->chartConfig->getDefaultLegendOptions();
        $this->config['legend'] = array_merge($defaultOptions, $options);
        return $this;
    }
    
    /**
     * Aktualisiert die allgemeinen Diagramm-Konfigurationen
     * 
     * @param array $config Neue Konfigurationsparameter
     * @return PHPChart Fluent Interface
     */
    public function updateConfig($config = []) {
        $this->config = $this->chartConfig->mergeConfig($config, $this->config);
        return $this;
    }
    
    /**
     * Generiert das Diagramm basierend auf den gesetzten Daten und Optionen
     * 
     * @return PHPChart Fluent Interface
     */
    public function generate() {
        // Setze das Flag für horizontale Balken in der Achsenklasse
        $this->chartAxes->setHorizontalBars($this->hasHorizontalBars);
        
        // Initialisiere das SVG-Container
        $this->svgOutput = $this->chartSVG->initSVG($this->config['width'], $this->config['height']);
        
        // Berechne das nutzbare Zeichengebiet nach Abzug der Ränder
        $chartArea = $this->calculateChartArea();
        
        // Wenn keine Achsen definiert wurden, Standardachsen erstellen
        if (empty($this->axes['x'])) {
            $this->addXAxis();
        }
        if (empty($this->axes['y'])) {
            $this->addYAxis();
        }
        
        // Bereite die Achsen vor
        $this->prepareAxes($chartArea);
        
        // Rendere den Hintergrund und das Gitter
        $this->renderBackground($chartArea);
        
        // Rendere die Datenreihen basierend auf ihrem Typ
        $this->renderSeries($chartArea);
        
        // Rendere die Achsen
        $this->renderAxes();
        
        // Rendere die Legende, falls aktiviert
        if ($this->config['legend']['enabled']) {
            $this->renderLegend($chartArea);
        }
        
        // Schließe den SVG-Container
        $this->svgOutput .= $this->chartSVG->closeSVG();
        
        return $this;
    }
    
    /**
     * Berechnet den verfügbaren Zeichenbereich unter Berücksichtigung von Rändern und Achsen
     * 
     * @return array Daten zum Zeichenbereich (x, y, width, height)
     */
    private function calculateChartArea() {
        // Implementierung folgt
        $margin = $this->config['margin'];
        
        // Standardzeichenbereich mit Rändern
        $chartArea = [
            'x' => $margin['left'],
            'y' => $margin['top'],
            'width' => $this->config['width'] - $margin['left'] - $margin['right'],
            'height' => $this->config['height'] - $margin['top'] - $margin['bottom']
        ];
        
        return $chartArea;
    }
    
    /**
     * Bereitet die Achsen für die Darstellung vor
     * 
     * @param array $chartArea Daten zum Zeichenbereich
     */
    private function prepareAxes($chartArea) {
        // X-Achsen vorbereiten
        foreach ($this->axes['x'] as $id => &$xAxis) {
            $this->chartAxes->prepareXAxis($xAxis, $id, $this->xValues, $this->yValues, $chartArea);
        }
        
        // Y-Achsen vorbereiten
        foreach ($this->axes['y'] as $id => &$yAxis) {
            $this->chartAxes->prepareYAxis($yAxis, $id, $this->yValues, $chartArea);
        }
    }
    
    /**
     * Rendert den Hintergrund und das Gitter des Diagramms
     * 
     * @param array $chartArea Daten zum Zeichenbereich
     */
    private function renderBackground($chartArea) {
        // Hintergrund rendern
        if ($this->config['background']['enabled']) {
            $this->svgOutput .= $this->chartSVG->renderBackground(
                $chartArea,
                $this->config['background']['color'],
                $this->config['background']['borderRadius']
            );
        }
        
        // Gitter rendern, falls aktiviert
        if ($this->config['grid']['enabled']) {
            $this->svgOutput .= $this->chartSVG->renderGrid(
                $chartArea,
                $this->axes,
                $this->config['grid']
            );
        }
    }
    
    /**
     * Rendert alle Datenreihen basierend auf ihrem Typ
     * 
     * @param array $chartArea Daten zum Zeichenbereich
     */
    private function renderSeries($chartArea) {
        // Gruppiere Serien nach Typ für optimiertes Rendern
        $seriesByType = [];
        
        foreach ($this->series as $seriesName => $seriesOptions) {
            $type = $seriesOptions['type'];
            if (!isset($seriesByType[$type])) {
                $seriesByType[$type] = [];
            }
            $seriesByType[$type][$seriesName] = $seriesOptions;
        }
        
        // Rendere nach Typ
        foreach ($seriesByType as $type => $seriesGroup) {
            switch ($type) {
                case 'bar':
                    $barChart = new ChartBarChart();
                    $this->svgOutput .= $barChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                    
                case 'line':
                    $lineChart = new ChartLineChart();
                    $this->svgOutput .= $lineChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                    
                case 'spline':
                    $lineChart = new ChartLineChart();
                    $this->svgOutput .= $lineChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                    
                case 'waterfall':
                    $waterfallChart = new ChartWaterfallChart();
                    $this->svgOutput .= $waterfallChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                    
                // Weitere Chart-Typen werden später implementiert
                
                default:
                    // Unbekannter Chart-Typ - ignorieren oder Fehler werfen
                    break;
            }
        }
    }
    
    /**
     * Rendert alle Achsen des Diagramms
     */
    private function renderAxes() {
        // X-Achsen rendern
        foreach ($this->axes['x'] as $id => $xAxis) {
            $this->svgOutput .= $this->chartAxes->renderXAxis($xAxis, $id);
        }
        
        // Y-Achsen rendern
        foreach ($this->axes['y'] as $id => $yAxis) {
            $this->svgOutput .= $this->chartAxes->renderYAxis($yAxis, $id);
        }
    }
    
    /**
     * Rendert die Legende des Diagramms
     * 
     * @param array $chartArea Daten zum Zeichenbereich
     */
    private function renderLegend($chartArea) {
        $this->svgOutput .= $this->chartLegend->render(
            $this->series,
            $chartArea,
            $this->config['legend'],
            $this->axes  // Übergebe die Achsen-Informationen für bessere Positionierung
        );
    }
    
    /**
     * Gibt das generierte SVG zurück
     * 
     * @return string SVG-Code des Diagramms
     */
    public function getSVG() {
        return $this->svgOutput;
    }
    
    /**
     * Gibt das generierte SVG direkt aus
     */
    public function display() {
        echo $this->svgOutput;
    }
    
    /**
     * Speichert das generierte SVG in eine Datei
     * 
     * @param string $filename Dateiname (inkl. Pfad) zum Speichern
     * @return bool Erfolg des Speichervorgangs
     */
    public function saveToFile($filename) {
        return file_put_contents($filename, $this->svgOutput) !== false;
    }
}
?>