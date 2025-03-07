<?php
/**
 * PHPChart - Eine umfassende PHP-Klasse zur Erstellung von SVG-Diagrammen
 * 
 * Diese Klasse ermöglicht die Erstellung verschiedener Diagrammtypen mit
 * anpassbaren Optionen für Darstellung, Achsen, Legenden und mehr.
 * 
 * 
 * @version 1.20
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
include_once('chart.class.bubble.php');
include_once('chart.class.radar.php');
include_once('chart.class.multipie.php');
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
     * @var bool Flag für Kreisdiagramme
     */
    private $hasPieCharts = false;
    
    /**
     * @var bool Flag für Multi-Pie/Donut Diagramme
     */
    private $hasMultiPieCharts = false;
    
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
        
        // Prüfen, ob es sich um Pie-/Donut-Charts handelt
        if (isset($seriesOptions['type']) && ($seriesOptions['type'] === 'pie')) {
            $this->hasPieCharts = true;
        }
        
        // Prüfen, ob es sich um Multi-Pie/Donut-Charts handelt
        if (isset($seriesOptions['type']) && ($seriesOptions['type'] === 'multipie')) {
            $this->hasMultiPieCharts = true;
            // Multi-Pie/Donut Charts werden wie Pie-Charts behandelt (keine Achsen)
            $this->hasPieCharts = true;
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
        
        // Für nicht-Pie/MultiPie-Charts: Achsen vorbereiten und rendern
        if (!$this->hasPieChartsOnly()) {
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
        } else {
            // Für reine Pie/MultiPie-Charts nur den Hintergrund rendern ohne Gitter
            if ($this->config['background']['enabled']) {
                $this->svgOutput .= $this->chartSVG->createRect(
                    0, 0, $this->config['width'], $this->config['height'],
                    [
                        'fill' => $this->config['background']['color'],
                        'rx' => $this->config['background']['borderRadius'],
                        'ry' => $this->config['background']['borderRadius']
                    ]
                );
            }
        }
        
        // Diagrammtitel rendern
        if (isset($this->config['title']) && $this->config['title']['enabled'] && !empty($this->config['title']['text'])) {
            $titleX = $this->config['width'] / 2 + (isset($this->config['title']['offsetX']) ? $this->config['title']['offsetX'] : 0);
            $titleY = $this->config['margin']['top'] / 2 + (isset($this->config['title']['offsetY']) ? $this->config['title']['offsetY'] : 0);
            
            $this->svgOutput .= $this->chartSVG->createText(
                $titleX,
                $titleY,
                $this->config['title']['text'],
                [
                    'fontFamily' => isset($this->config['title']['fontFamily']) ? $this->config['title']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                    'fontSize' => isset($this->config['title']['fontSize']) ? $this->config['title']['fontSize'] : 18,
                    'fontWeight' => isset($this->config['title']['fontWeight']) ? $this->config['title']['fontWeight'] : 'bold',
                    'fill' => isset($this->config['title']['color']) ? $this->config['title']['color'] : '#333333',
                    'textAnchor' => 'middle'
                ]
            );
        }
        
        // Rendere die Datenreihen basierend auf ihrem Typ
        $this->renderSeries($chartArea);
        
        // Rendere die Achsen nur für nicht-Pie/MultiPie-Charts
        if (!$this->hasPieChartsOnly()) {
            $this->renderAxes();
        }
        
        // Rendere die Legende, falls aktiviert
        if ($this->config['legend']['enabled']) {
            $this->renderLegend($chartArea);
        }
        
        // Schließe den SVG-Container
        $this->svgOutput .= $this->chartSVG->closeSVG();
        
        return $this;
    }
    
    /**
     * Überprüft, ob das Diagramm nur aus Pie-/MultiPie-Charts besteht
     * 
     * @return bool True, wenn nur Pie-/MultiPie-Charts vorhanden sind
     */
    private function hasPieChartsOnly() {
        if (!$this->hasPieCharts) {
            return false;
        }
        
        foreach ($this->series as $seriesOptions) {
            if (isset($seriesOptions['type']) && 
                $seriesOptions['type'] !== 'pie' && 
                $seriesOptions['type'] !== 'multipie') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Berechnet den verfügbaren Zeichenbereich unter Berücksichtigung von Rändern und Achsen
     * 
     * @return array Daten zum Zeichenbereich (x, y, width, height)
     */
    private function calculateChartArea() {
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
                    
                case 'area':
                    $areaChart = new ChartAreaChart();
                    $this->svgOutput .= $areaChart->render(
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
                    
                case 'pie':
                    // Für Pie-Charts den gesamten verfügbaren Platz nutzen
                    $pieChartArea = $this->hasPieChartsOnly() 
                        ? [
                            'x' => $this->config['margin']['left'],
                            'y' => $this->config['margin']['top'],
                            'width' => $this->config['width'] - $this->config['margin']['left'] - $this->config['margin']['right'],
                            'height' => $this->config['height'] - $this->config['margin']['top'] - $this->config['margin']['bottom']
                          ]
                        : $chartArea;
                    
                    $pieChart = new ChartPieChart();
                    $this->svgOutput .= $pieChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        null,  // Keine Achsen für Pie-Charts
                        $pieChartArea,
                        $this->config
                    );
                    break;
                
                case 'multipie':
                    // Für Multi-Pie Charts den gesamten verfügbaren Platz nutzen
                    $multiPieChartArea = $this->hasPieChartsOnly() 
                        ? [
                            'x' => $this->config['margin']['left'],
                            'y' => $this->config['margin']['top'],
                            'width' => $this->config['width'] - $this->config['margin']['left'] - $this->config['margin']['right'],
                            'height' => $this->config['height'] - $this->config['margin']['top'] - $this->config['margin']['bottom']
                          ]
                        : $chartArea;
                    
                    $multiPieChart = new ChartMultiPieChart();
                    $this->svgOutput .= $multiPieChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        null,  // Keine Achsen für Multi-Pie Charts
                        $multiPieChartArea,
                        $this->config
                    );
                    break;
                    
                case 'bubble':
                    $bubbleChart = new ChartBubbleChart();
                    $this->svgOutput .= $bubbleChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                    
                case 'polar':
                    $polarChart = new ChartPolarChart();
                    $this->svgOutput .= $polarChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                
                case 'scatter':
                    $scatterChart = new ChartScatterChart();
                    $this->svgOutput .= $scatterChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                
                case 'radar':
                    $radarChart = new ChartRadarChart();
                    $this->svgOutput .= $radarChart->render(
                        $seriesGroup,
                        $this->xValues,
                        $this->yValues,
                        $this->axes,
                        $chartArea,
                        $this->config
                    );
                    break;
                
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

    /**
    * Gibt das generierte SVG in einem HTML-Dokument zurück
    * 
    * @return string HTML-Dokument mit dem eingebetteten SVG
    */
    public function getHTML() {
        $svg = $this->getSVG();
    
        $html = '<!DOCTYPE html>
        <html lang="de">
            <head>
                <meta charset="UTF-8">
                <style>
                    html, body {
                    margin: 10;
                    padding: 0;
                    overflow: hidden;
                    height: 100%;
                    width: 100%;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
            
            </style>
        </head>
    <body>';
        $html .= $svg;
        $html .= '</body> </html>';
    
    return $html;
    }
    
    /**
     * Erzeugt eine String-Repräsentation des Diagramms
     *
     * Diese Methode wird aufgerufen, wenn das Objekt als String verwendet wird.
     * 
     * @return string SVG-Code des Diagramms
     */
    public function __toString() {
        return $this->svgOutput;
    }
}
?>
