<?php
/**
 * ChartAreaChart - Flächendiagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Flächendiagrammen zuständig,
 * einschließlich normaler Flächendiagramme und gestapelter Flächendiagramme.
 * 
 * @version 1.3
 */
class ChartAreaChart {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
    /**
     * @var ChartAxes Instanz der Achsen-Klasse
     */
    private $axes;
    
    /**
     * Cache für generierte Gradienten-IDs
     * @var array
     */
    private $gradientCache = [];
    
    /**
     * Konstruktor - Initialisiert die benötigten Objekte
     */
    public function __construct() {
        $this->utils = new ChartUtils();
        $this->svg = new ChartSVG();
        $this->axes = new ChartAxes();
    }
    
    /**
     * Rendert ein Flächendiagramm
     * 
     * @param array $seriesGroup Gruppe von Flächendiagramm-Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Flächendiagramms
     */
    public function render($seriesGroup, $xValues, $yValues, $axes, $chartArea, $config) {
        // Initialisiere Gradienten-Cache vor jeder Nutzung
        $this->gradientCache = [];
        
        // Erstelle Gradienten für alle Serien, die diese benötigen
        $this->prepareGradients($seriesGroup);
        
        // Initialisiere Ausgabe
        $output = '';
        
        // Erzeuge Defs-Sektion für Gradienten
        $defs = $this->generateGradientDefsSection();
        if (!empty($defs)) {
            $output .= $this->svg->createDefs($defs);
        }
        
        // Aktualisiere die Serien mit Gradienten-IDs
        $updatedSeriesGroup = $this->applyGradientIds($seriesGroup);
        
        // Prüfe, ob es gestapelte Flächendiagramme gibt
        $hasStacked = false;
        foreach ($updatedSeriesGroup as $seriesOptions) {
            if (isset($seriesOptions['stacked']) && $seriesOptions['stacked']) {
                $hasStacked = true;
                break;
            }
        }
        
        if ($hasStacked) {
            // Bei gestapelten Flächendiagrammen sollten wir die Y-Achsen anpassen
            $this->adjustYAxisForStackedAreas($updatedSeriesGroup, $yValues, $axes);
            
            // Rendere gestapelte Flächendiagramme
            $output .= $this->renderStackedAreas($updatedSeriesGroup, $xValues, $yValues, $axes, $chartArea);
        } else {
            // Rendere normale Flächendiagramme
            foreach ($updatedSeriesGroup as $seriesName => $seriesOptions) {
                $output .= $this->renderArea($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea);
            }
        }
        
        return $output;
    }
    
    /**
     * Passt die Y-Achsen für gestapelte Flächendiagramme an
     * 
     * @param array $seriesGroup Gruppe von Flächendiagramm-Serien
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     */
    private function adjustYAxisForStackedAreas($seriesGroup, $yValues, &$axes) {
        // Gruppiere Serien nach Stapelgruppe und Achsen
        $stackGroups = [];
        
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            if (!isset($seriesOptions['stacked']) || !$seriesOptions['stacked']) {
                continue;
            }
            
            $stackGroup = isset($seriesOptions['stackGroup']) ? $seriesOptions['stackGroup'] : 'default';
            $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
            
            if (!isset($stackGroups[$stackGroup][$yAxisId])) {
                $stackGroups[$stackGroup][$yAxisId] = [];
            }
            
            $stackGroups[$stackGroup][$yAxisId][$seriesName] = $seriesOptions;
        }
        
        // Berechne maximale Stapelhöhe für jede Y-Achse
        $yAxisMaxValues = [];
        
        foreach ($stackGroups as $stackGroup => $yAxes) {
            foreach ($yAxes as $yAxisId => $seriesInGroup) {
                // Sammle alle einzigartigen X-Koordinaten
                $stackSums = [];
                
                foreach ($seriesInGroup as $seriesName => $seriesOptions) {
                    $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
                    
                    for ($i = 0; $i < count($seriesY); $i++) {
                        if (!isset($stackSums[$i])) {
                            $stackSums[$i] = 0;
                        }
                        
                        $yValue = isset($seriesY[$i]) ? $seriesY[$i] : 0;
                        if (is_numeric($yValue)) {
                            $stackSums[$i] += $yValue;
                        }
                    }
                }
                
                // Finde den maximalen Stapelwert
                $maxStackValue = !empty($stackSums) ? max($stackSums) : 0;
                
                // Speichere den maximalen Wert für diese Y-Achse
                if (!isset($yAxisMaxValues[$yAxisId]) || $maxStackValue > $yAxisMaxValues[$yAxisId]) {
                    $yAxisMaxValues[$yAxisId] = $maxStackValue;
                }
            }
        }
        
        // Aktualisiere die maximalen Werte für die Y-Achsen
        foreach ($yAxisMaxValues as $yAxisId => $maxValue) {
            // Nur aktualisieren, wenn der berechnete Wert größer ist als der vorhandene max-Wert
            // oder wenn kein max-Wert gesetzt ist
            if (!isset($axes['y'][$yAxisId]['max']) || $axes['y'][$yAxisId]['max'] === null || $maxValue > $axes['y'][$yAxisId]['max']) {
                // Füge 10% Platz oben hinzu für bessere Darstellung
                $axes['y'][$yAxisId]['max'] = ceil($maxValue * 1.1);
            }
        }
    }
    
    /**
     * Erstellt Gradienten für alle Serien, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Flächendiagramm-Serien
     */
    private function prepareGradients($seriesGroup) {
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            // Gradienten für die Hauptserie prüfen
            if (isset($seriesOptions['gradient']) && isset($seriesOptions['gradient']['enabled']) && $seriesOptions['gradient']['enabled']) {
                // Generiere eine sichere ID ohne Leerzeichen oder ungültige Zeichen
                $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                $gradientId = 'gradient_' . $safeSeriesName . '_' . $this->utils->generateId();
                
                // Speichere Gradientendefinition im Cache
                $this->gradientCache[$seriesName] = [
                    'id' => $gradientId,
                    'options' => $seriesOptions['gradient'],
                    'color' => isset($seriesOptions['color']) ? $seriesOptions['color'] : '#000000'
                ];
            }
        }
    }
    
    /**
     * Generiert alle Gradienten-Definitionen
     * 
     * @return string SVG-Gradient-Definitionen
     */
    private function generateGradientDefsSection() {
        $defs = '';
        
        foreach ($this->gradientCache as $key => $gradientInfo) {
            $gradientId = $gradientInfo['id'];
            $gradientOptions = $gradientInfo['options'];
            $baseColor = $gradientInfo['color'];
            
            $type = isset($gradientOptions['type']) ? $gradientOptions['type'] : 'linear';
            $stops = [];
            
            // Mehrere Farben für den Gradienten ermöglichen
            if (isset($gradientOptions['colors']) && !empty($gradientOptions['colors'])) {
                // Wenn ein Array von Farben angegeben ist
                $colors = $gradientOptions['colors'];
                $stopCount = count($colors);
                
                // Prüfe, ob benutzerdefinierte Stops vorhanden sind
                $customStops = isset($gradientOptions['stops']) && !empty($gradientOptions['stops']) 
                              ? $gradientOptions['stops'] : [];
                
                // Erzeuge Stops basierend auf den Farben
                for ($i = 0; $i < $stopCount; $i++) {
                    $offset = isset($customStops[$i]) ? $customStops[$i] : ($i * (100 / max(1, $stopCount - 1))) . '%';
                    $stops[] = [
                        'offset' => $offset,
                        'color' => $colors[$i],
                        'opacity' => 1.0
                    ];
                }
            } else {
                // Fallback auf Start- und Endfarbe (Kompatibilität)
                $startColor = !empty($gradientOptions['startColor']) ? 
                              $gradientOptions['startColor'] : 
                              $baseColor;
                $endColor = !empty($gradientOptions['endColor']) ? 
                            $gradientOptions['endColor'] : 
                            $this->utils->alphaBlend($baseColor, 0.5);
                
                $stops = [
                    ['offset' => '0%', 'color' => $startColor, 'opacity' => 1.0],
                    ['offset' => '100%', 'color' => $endColor, 'opacity' => 1.0]
                ];
            }
            
            if ($type === 'linear') {
                $angle = isset($gradientOptions['angle']) ? $gradientOptions['angle'] : 90;
                
                // Konvertiere Winkel in Gradient-Koordinaten
                $angleRad = deg2rad($angle);
                $x1 = 50 - cos($angleRad) * 50;
                $y1 = 50 - sin($angleRad) * 50;
                $x2 = 50 + cos($angleRad) * 50;
                $y2 = 50 + sin($angleRad) * 50;
                
                $lineGradientOptions = [
                    'x1' => $x1 . '%',
                    'y1' => $y1 . '%',
                    'x2' => $x2 . '%',
                    'y2' => $y2 . '%'
                ];
                
                $defs .= $this->svg->createLinearGradient($gradientId, $stops, $lineGradientOptions);
            } else { // 'radial'
                $defs .= $this->svg->createRadialGradient($gradientId, $stops);
            }
        }
        
        return $defs;
    }
    
    /**
     * Aktualisiert die Serien mit den korrekten Gradienten-IDs
     * 
     * @param array $seriesGroup Originale Seriengruppe
     * @return array Aktualisierte Seriengruppe mit Gradienten-IDs
     */
    private function applyGradientIds($seriesGroup) {
        $updatedSeriesGroup = $seriesGroup;
        
        foreach ($this->gradientCache as $key => $gradientInfo) {
            if (isset($updatedSeriesGroup[$key])) {
                $updatedSeriesGroup[$key]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
            }
        }
        
        return $updatedSeriesGroup;
    }
    
    /**
     * Rendert ein einzelnes Flächendiagramm
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente des Flächendiagramms
     */
    private function renderArea($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine X-Werte angegeben sind, verwende Indizes
        if (empty($seriesX)) {
            $seriesX = range(0, count($seriesY) - 1);
        }
        
        // Sammele die Punkte für das Flächendiagramm
        $points = [];
        $validYValues = []; // Für spätere Verwendung bei Punkten und Labels
        
        foreach ($seriesY as $idx => $yValue) {
            if (!isset($seriesX[$idx])) continue;
            $xValue = $seriesX[$idx];
            
            // Ignorieren, wenn der Y-Wert null oder nicht numerisch ist, 
            // es sei denn, connectNulls ist aktiviert
            $connectNulls = isset($seriesOptions['line']) && isset($seriesOptions['line']['connectNulls']) && $seriesOptions['line']['connectNulls'];
            if (($yValue === null || $yValue === '' || !is_numeric($yValue)) && !$connectNulls) {
                continue;
            }
            
            // X-Koordinate basierend auf dem Achsentyp berechnen
            if ($xAxis['type'] === 'category') {
                $x = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
            } else {
                $x = $this->axes->convertXValueToCoordinate($xValue, $xAxis, $chartArea);
            }
            
            // Y-Koordinate berechnen
            $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
            
            $points[] = [$x, $y];
            $validYValues[] = $yValue;
        }
        
        // Wenn keine Punkte vorhanden sind, nichts rendern
        if (empty($points)) {
            return '';
        }
        
        $output = '';
        
        // Finde die Y-Koordinate für Y=0
        $zeroY = $this->axes->convertYValueToCoordinate(0, $yAxis, $chartArea);
        
        // Erzeuge den Pfad für die Fläche
        $path = $this->createAreaPath($points, $chartArea, $zeroY);
        
        // Stil-Optionen für die Fläche
        $areaColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        $areaOpacity = isset($seriesOptions['area']) && isset($seriesOptions['area']['fillOpacity']) ? 
                      $seriesOptions['area']['fillOpacity'] : 0.4;
        $strokeWidth = isset($seriesOptions['area']) && isset($seriesOptions['area']['strokeWidth']) ? 
                     $seriesOptions['area']['strokeWidth'] : 2;
        
        // Prüfe, ob ein Gradient für die Fläche verwendet werden soll
        $fillColor = $areaColor;
        if (isset($seriesOptions['gradient']) && isset($seriesOptions['gradient']['enabled']) && $seriesOptions['gradient']['enabled']) {
            if (isset($seriesOptions['gradientId'])) {
                $fillColor = $seriesOptions['gradientId'];
            }
        }
        
        // Rendere die Fläche
        $output .= $this->svg->createPath(
            $path,
            [
                'fill' => $fillColor,
                'fillOpacity' => $areaOpacity,
                'stroke' => $areaColor,
                'strokeWidth' => $strokeWidth
            ]
        );
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            $output .= $this->renderPoints($points, $seriesOptions, $validYValues);
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $output .= $this->renderDataLabels($points, $seriesOptions, $validYValues);
        }
        
        return $output;
    }
    
    /**
     * Rendert ein gestapeltes Flächendiagramm
     * 
     * @param array $seriesGroup Gruppe von Flächendiagramm-Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente des gestapelten Flächendiagramms
     */
    private function renderStackedAreas($seriesGroup, $xValues, $yValues, $axes, $chartArea) {
        // Initialisiere Ausgabe
        $output = '';
        
        // Gruppiere Serien nach Stapelgruppe
        $stackGroups = [];
        
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            if (!isset($seriesOptions['stacked']) || !$seriesOptions['stacked']) {
                continue; // Überspringe nicht-gestapelte Serien
            }
            
            $stackGroup = isset($seriesOptions['stackGroup']) ? $seriesOptions['stackGroup'] : 'default';
            
            if (!isset($stackGroups[$stackGroup])) {
                $stackGroups[$stackGroup] = [];
            }
            
            $stackGroups[$stackGroup][$seriesName] = $seriesOptions;
        }
        
        // Rendere jede Stapelgruppe
        foreach ($stackGroups as $stackGroup => $stackSeries) {
            $output .= $this->renderStackGroup($stackGroup, $stackSeries, $xValues, $yValues, $axes, $chartArea);
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Stapelgruppe von Flächendiagrammen
     * 
     * @param string $stackGroup Name der Stapelgruppe
     * @param array $seriesInGroup Serien in dieser Stapelgruppe
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Stapelgruppe
     */
    private function renderStackGroup($stackGroup, $seriesInGroup, $xValues, $yValues, $axes, $chartArea) {
        // Initialisiere Ausgabe
        $output = '';
        
        // Bestimme die zu verwendenden Achsen (erste Serie)
        $firstSeries = reset($seriesInGroup);
        $xAxisId = isset($firstSeries['xAxisId']) ? $firstSeries['xAxisId'] : 0;
        $yAxisId = isset($firstSeries['yAxisId']) ? $firstSeries['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Finde alle einzigartigen X-Werte über alle Serien hinweg
        $allXValues = [];
        
        foreach ($seriesInGroup as $seriesName => $seriesOptions) {
            $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
            
            if (empty($seriesX)) {
                // Wenn keine X-Werte angegeben sind, verwende Indizes
                $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
                $seriesX = range(0, count($seriesY) - 1);
            }
            
            foreach ($seriesX as $xValue) {
                if (!in_array($xValue, $allXValues)) {
                    $allXValues[] = $xValue;
                }
            }
        }
        
        // Sortiere X-Werte (wichtig für korrekte Darstellung)
        sort($allXValues);
        
        // Initialisiere Stapel für jede X-Koordinate
        $stack = [];
        foreach ($allXValues as $xValue) {
            $stack[$xValue] = 0;
        }
        
        // Rendere Serien in umgekehrter Reihenfolge, damit die erste Serie zuoberst liegt
        $reversedSeries = array_reverse($seriesInGroup, true);
        
        // Finde die Y-Koordinate für Y=0
        $zeroY = $this->axes->convertYValueToCoordinate(0, $yAxis, $chartArea);
        
        foreach ($reversedSeries as $seriesName => $seriesOptions) {
            $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
            $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
            
            // Wenn keine X-Werte angegeben sind, verwende Indizes
            if (empty($seriesX)) {
                $seriesX = range(0, count($seriesY) - 1);
            }
            
            // Baue eine Lookup-Tabelle für X-Y-Paare
            $xyPairs = [];
            for ($i = 0; $i < count($seriesX); $i++) {
                if (isset($seriesY[$i])) {
                    $xyPairs[$seriesX[$i]] = $seriesY[$i];
                }
            }
            
            // Erzeuge Punkte für die aktuelle Serie mit dem aktuellen Stapel
            $topPoints = [];
            $bottomPoints = [];
            $validYValues = [];
            
            // Sammele die Punkte für das Stapel-Flächendiagramm
            foreach ($allXValues as $xValue) {
                // Finde den Y-Wert für diesen X-Wert über die Lookup-Tabelle
                $yValue = isset($xyPairs[$xValue]) ? $xyPairs[$xValue] : 0;
                
                // Ignorieren, wenn der Y-Wert null oder nicht numerisch ist
                if ($yValue === null || $yValue === '' || !is_numeric($yValue)) {
                    $yValue = 0;
                }
                
                // Stapelwert für diesen X-Wert abrufen
                $stackValue = $stack[$xValue];
                
                // X-Koordinate basierend auf dem Achsentyp berechnen
                if ($xAxis['type'] === 'category') {
                    $x = $this->axes->convertXValueToCoordinate(array_search($xValue, $allXValues), $xAxis, $chartArea);
                } else {
                    $x = $this->axes->convertXValueToCoordinate($xValue, $xAxis, $chartArea);
                }
                
                // Y-Koordinate für obere Kante der Fläche
                $topY = $this->axes->convertYValueToCoordinate($stackValue + $yValue, $yAxis, $chartArea);
                
                // Y-Koordinate für untere Kante der Fläche (vorherigen Stapelwert)
                $bottomY = $this->axes->convertYValueToCoordinate($stackValue, $yAxis, $chartArea);
                
                // Speichere Koordinaten
                $topPoints[] = [$x, $topY];
                $bottomPoints[] = [$x, $bottomY];
                $validYValues[] = $yValue;
                
                // Aktualisiere Stapel für diesen X-Wert
                $stack[$xValue] += $yValue;
            }
            
            // Wenn keine Punkte vorhanden sind, nichts rendern
            if (empty($topPoints)) {
                continue;
            }
            
            // Erstelle den Pfad für die gestapelte Fläche
            $path = $this->createStackedAreaPath($topPoints, $bottomPoints, $chartArea);
            
            // Stil-Optionen für die Fläche
            $areaColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
            $areaOpacity = isset($seriesOptions['area']) && isset($seriesOptions['area']['fillOpacity']) ? 
                          $seriesOptions['area']['fillOpacity'] : 0.4;
            $strokeWidth = isset($seriesOptions['area']) && isset($seriesOptions['area']['strokeWidth']) ? 
                         $seriesOptions['area']['strokeWidth'] : 2;
            
            // Prüfe, ob ein Gradient für die Fläche verwendet werden soll
            $fillColor = $areaColor;
            if (isset($seriesOptions['gradient']) && isset($seriesOptions['gradient']['enabled']) && $seriesOptions['gradient']['enabled']) {
                if (isset($seriesOptions['gradientId'])) {
                    $fillColor = $seriesOptions['gradientId'];
                }
            }
            
            // Rendere die Fläche
            $output .= $this->svg->createPath(
                $path,
                [
                    'fill' => $fillColor,
                    'fillOpacity' => $areaOpacity,
                    'stroke' => $areaColor,
                    'strokeWidth' => $strokeWidth
                ]
            );
            
            // Punkte rendern, falls aktiviert
            if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
                $output .= $this->renderPoints($topPoints, $seriesOptions, $validYValues);
            }
            
            // Datenwertbeschriftungen rendern, falls aktiviert
            if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                $output .= $this->renderDataLabels($topPoints, $seriesOptions, $validYValues);
            }
        }
        
        return $output;
    }
    
    /**
     * Erzeugt einen SVG-Pfad für ein normales Flächendiagramm
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $chartArea Daten zum Zeichenbereich
     * @param float $zeroY Y-Koordinate der Nulllinie
     * @return string SVG-Pfad-Daten
     */
    private function createAreaPath($points, $chartArea, $zeroY) {
        if (empty($points)) {
            return '';
        }
        
        $path = '';
        
        // Beginne den Pfad an der Y-Achse auf Höhe der Nulllinie
        $path = 'M' . $chartArea['x'] . ',' . $zeroY;
        
        // Linie zur Y-Achse auf Höhe des ersten Punktes
        $path .= ' L' . $chartArea['x'] . ',' . $points[0][1];
        
        // Linie zum ersten Punkt
        $path .= ' L' . $points[0][0] . ',' . $points[0][1];
        
        // Linie zu jedem weiteren Punkt
        for ($i = 1; $i < count($points); $i++) {
            $path .= ' L' . $points[$i][0] . ',' . $points[$i][1];
        }
        
        // Rechter Rand des Diagramms
        $rightEdge = $chartArea['x'] + $chartArea['width'];
        
        // Linie zum rechten Rand auf Höhe des letzten Punktes (kein Abstand zum Ende)
        $path .= ' L' . $rightEdge . ',' . $points[count($points) - 1][1];
        
        // Linie zum rechten Rand auf Höhe der Nulllinie
        $path .= ' L' . $rightEdge . ',' . $zeroY;
        
        // Pfad schließen (zurück zum Startpunkt)
        $path .= ' Z';
        
        return $path;
    }
    
    /**
     * Erzeugt einen SVG-Pfad für ein gestapeltes Flächendiagramm
     * 
     * @param array $topPoints Array mit Punkten für die obere Kante als [x, y]-Arrays
     * @param array $bottomPoints Array mit Punkten für die untere Kante als [x, y]-Arrays
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Pfad-Daten
     */
    private function createStackedAreaPath($topPoints, $bottomPoints, $chartArea) {
        if (empty($topPoints) || empty($bottomPoints) || count($topPoints) !== count($bottomPoints)) {
            return '';
        }
        
        $path = '';
        $pointCount = count($topPoints);
        
        if ($pointCount === 0) {
            return '';
        }
        
        $rightEdge = $chartArea['x'] + $chartArea['width'];
        
        // Beginne den Pfad an der Y-Achse auf Höhe des ersten unteren Punktes
        $path = 'M' . $chartArea['x'] . ',' . $bottomPoints[0][1];
        
        // Linie zum ersten unteren Punkt
        $path .= ' L' . $bottomPoints[0][0] . ',' . $bottomPoints[0][1];
        
        // Linie zu jedem weiteren unteren Punkt von links nach rechts
        for ($i = 1; $i < $pointCount; $i++) {
            $path .= ' L' . $bottomPoints[$i][0] . ',' . $bottomPoints[$i][1];
        }
        
        // Linie zum rechten Rand auf Höhe des letzten unteren Punktes
        $path .= ' L' . $rightEdge . ',' . $bottomPoints[$pointCount - 1][1];
        
        // Linie zum rechten Rand auf Höhe des letzten oberen Punktes
        $path .= ' L' . $rightEdge . ',' . $topPoints[$pointCount - 1][1];
        
        // Linie zum letzten oberen Punkt
        $path .= ' L' . $topPoints[$pointCount - 1][0] . ',' . $topPoints[$pointCount - 1][1];
        
        // Linie zu jedem weiteren oberen Punkt von rechts nach links
        for ($i = $pointCount - 2; $i >= 0; $i--) {
            $path .= ' L' . $topPoints[$i][0] . ',' . $topPoints[$i][1];
        }
        
        // Linie zur Y-Achse auf Höhe des ersten oberen Punktes
        $path .= ' L' . $chartArea['x'] . ',' . $topPoints[0][1];
        
        // Pfad schließen (zurück zum Startpunkt)
        $path .= ' Z';
        
        return $path;
    }
    
    /**
     * Rendert die Punkte einer Linie
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $seriesOptions Optionen für die Serie
     * @param array $yValues Y-Werte für die Punkte
     * @return string SVG-Elemente der Punkte
     */
    private function renderPoints($points, $seriesOptions, $yValues) {
        $output = '';
        
        // Punktoptionen
        $pointSize = isset($seriesOptions['point']['size']) ? $seriesOptions['point']['size'] : 5;
        $pointColor = isset($seriesOptions['point']['color']) && $seriesOptions['point']['color'] ? 
                     $seriesOptions['point']['color'] : 
                     (!empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000');
        $pointShape = isset($seriesOptions['point']['shape']) ? $seriesOptions['point']['shape'] : 'circle';
        $borderColor = isset($seriesOptions['point']['borderColor']) ? $seriesOptions['point']['borderColor'] : '';
        $borderWidth = isset($seriesOptions['point']['borderWidth']) ? $seriesOptions['point']['borderWidth'] : 1;
        
        // Rendere jeden Punkt
        foreach ($points as $idx => $point) {
            $x = $point[0];
            $y = $point[1];
            
            switch ($pointShape) {
                case 'circle':
                    $output .= $this->svg->createCircle(
                        $x,
                        $y,
                        $pointSize / 2,
                        [
                            'fill' => $pointColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                case 'square':
                    $output .= $this->svg->createRect(
                        $x - $pointSize / 2,
                        $y - $pointSize / 2,
                        $pointSize,
                        $pointSize,
                        [
                            'fill' => $pointColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                case 'triangle':
                    $points = [
                        [$x, $y - $pointSize / 2],
                        [$x - $pointSize / 2, $y + $pointSize / 2],
                        [$x + $pointSize / 2, $y + $pointSize / 2]
                    ];
                    
                    $output .= $this->svg->createPolygon(
                        $points,
                        [
                            'fill' => $pointColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                case 'diamond':
                    $points = [
                        [$x, $y - $pointSize / 2],
                        [$x + $pointSize / 2, $y],
                        [$x, $y + $pointSize / 2],
                        [$x - $pointSize / 2, $y]
                    ];
                    
                    $output .= $this->svg->createPolygon(
                        $points,
                        [
                            'fill' => $pointColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                default:
                    // Standardmäßig Kreis
                    $output .= $this->svg->createCircle(
                        $x,
                        $y,
                        $pointSize / 2,
                        [
                            'fill' => $pointColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert die Datenwertbeschriftungen einer Linie
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $seriesOptions Optionen für die Serie
     * @param array $yValues Y-Werte für die Punkte
     * @return string SVG-Elemente der Datenwertbeschriftungen
     */
    private function renderDataLabels($points, $seriesOptions, $yValues) {
        $output = '';
        
        // Optionen für Datenwertbeschriftungen
        $offsetX = isset($seriesOptions['dataLabels']['offsetX']) ? $seriesOptions['dataLabels']['offsetX'] : 0;
        $offsetY = isset($seriesOptions['dataLabels']['offsetY']) ? $seriesOptions['dataLabels']['offsetY'] : -15;
        $fontFamily = isset($seriesOptions['dataLabels']['fontFamily']) ? $seriesOptions['dataLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif';
        $fontSize = isset($seriesOptions['dataLabels']['fontSize']) ? $seriesOptions['dataLabels']['fontSize'] : 11;
        $fontWeight = isset($seriesOptions['dataLabels']['fontWeight']) ? $seriesOptions['dataLabels']['fontWeight'] : 'normal';
        $color = isset($seriesOptions['dataLabels']['color']) ? $seriesOptions['dataLabels']['color'] : '#333333';
        $format = isset($seriesOptions['dataLabels']['format']) ? $seriesOptions['dataLabels']['format'] : '{y}';
        $rotation = isset($seriesOptions['dataLabels']['rotation']) ? $seriesOptions['dataLabels']['rotation'] : 0;
        
        // Datenwertbeschriftungen rendern
        foreach ($points as $idx => $point) {
            // Verwende den entsprechenden Y-Wert, falls vorhanden
            $yValue = isset($yValues[$idx]) ? $yValues[$idx] : null;
            
            if ($yValue === null || $yValue === '' || !is_numeric($yValue)) continue;
            
            $x = $point[0];
            $y = $point[1];
            
            // Formatierung des Labels
            $label = str_replace('{y}', $this->utils->formatNumber($yValue), $format);
            
            // Label rendern
            $output .= $this->svg->createText(
                $x + $offsetX,
                $y + $offsetY,
                $label,
                [
                    'fontFamily' => $fontFamily,
                    'fontSize' => $fontSize,
                    'fontWeight' => $fontWeight,
                    'fill' => $color,
                    'textAnchor' => 'middle',
                    'rotate' => $rotation
                ]
            );
        }
        
        return $output;
    }
}
?>