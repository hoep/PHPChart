<?php
/**
 * ChartMultiPieChart - Multi-Pie und Multi-Donut-Diagramme für das PHPChart-System
 * 
 * Diese Klasse ermöglicht die Darstellung mehrerer Pie-/Donut-Diagramme in einem Chart.
 * Die Diagramme können flexibel positioniert werden und unterstützen alle Features
 * der Standard-Pie/Donut-Diagramme.
 * 
 * @version 1.4
 */
class ChartMultiPieChart {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
    /**
     * @var ChartPieChart Instanz der PieChart-Klasse
     */
    private $pieChart;
    
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
        $this->pieChart = new ChartPieChart();
    }
    
    /**
     * Rendert ein Multi-Pie/Donut-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Pie-/Donut-Diagramm-Serien
     * @param array $xValues Array mit X-Werten (Kategorien)
     * @param array $yValues Array mit Y-Werten (Werte)
     * @param array $axes Achsendefinitionen (nicht verwendet für Multi-Pie-Charts)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Multi-Pie/Donut-Diagramms
     */
    public function render($seriesGroup, $xValues, $yValues, $axes, $chartArea, $config) {
        // Speichere die Config für später
        $this->config = $config;
        
        // Initialisiere Gradienten-Cache vor jeder Nutzung
        $this->gradientCache = [];
        
        // Erstelle Gradienten für alle Serien und Segmente, die diese benötigen
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
        
        // Gruppiere die Serien nach Gruppenname
        $seriesByGroup = $this->groupSeries($updatedSeriesGroup);
        
        // Berechne das Layout für die Gruppen
        $layout = $this->calculateLayout($seriesByGroup, $chartArea);
        
        // Rendere die Gruppen
        foreach ($seriesByGroup as $groupName => $groupSeries) {
            $output .= $this->renderGroup($groupName, $groupSeries, $layout, $xValues, $yValues);
        }
        
        return $output;
    }
    
    /**
     * Erstellt Gradienten für alle Serien und Segmente, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Pie-/Donut-Diagramm-Serien
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
            
            // Individuelle Segment-Gradienten prüfen
            if (isset($seriesOptions['segments']) && is_array($seriesOptions['segments'])) {
                foreach ($seriesOptions['segments'] as $index => $segmentOptions) {
                    // Prüfen, ob der Eintrag ein Gradient hat
                    if (isset($segmentOptions['gradient']) && isset($segmentOptions['gradient']['enabled']) && $segmentOptions['gradient']['enabled']) {
                        $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                        $gradientId = 'gradient_' . $safeSeriesName . '_segment_' . $index;
                        
                        // Speichere Gradientendefinition im Cache
                        $cacheKey = $seriesName . '_segment_' . $index;
                        $this->gradientCache[$cacheKey] = [
                            'id' => $gradientId,
                            'options' => $segmentOptions['gradient'],
                            'color' => isset($segmentOptions['color']) ? $segmentOptions['color'] : '#000000'
                        ];
                    }
                }
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
            
            // Erstelle den entsprechenden Gradienten
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
            // Prüfe verschiedene Schlüsseltypen
            if (strpos($key, '_segment_') !== false) {
                // Individuelles Segment nach Index
                list($seriesName, $rest) = explode('_segment_', $key);
                $index = intval($rest); // Extrahiere den Index als Zahl
                
                if (isset($updatedSeriesGroup[$seriesName]) && 
                    isset($updatedSeriesGroup[$seriesName]['segments']) && 
                    isset($updatedSeriesGroup[$seriesName]['segments'][$index])) {
                    $updatedSeriesGroup[$seriesName]['segments'][$index]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
                }
            } else {
                // Hauptserien-Farbe
                if (isset($updatedSeriesGroup[$key])) {
                    $updatedSeriesGroup[$key]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
                }
            }
        }
        
        return $updatedSeriesGroup;
    }
    
    /**
     * Gruppiert die Serien nach Gruppenname
     * 
     * @param array $seriesGroup Array mit allen Serien
     * @return array Gruppierte Serien
     */
    private function groupSeries($seriesGroup) {
        $seriesByGroup = [];
        
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            // Bestimme die Gruppe, zu der die Serie gehört
            $groupName = isset($seriesOptions['multipie']['group']) ? 
                       $seriesOptions['multipie']['group'] : 
                       'default';
            
            // Initialisiere die Gruppe, falls noch nicht vorhanden
            if (!isset($seriesByGroup[$groupName])) {
                $seriesByGroup[$groupName] = [];
            }
            
            // Füge die Serie zur Gruppe hinzu
            $seriesByGroup[$groupName][$seriesName] = $seriesOptions;
        }
        
        return $seriesByGroup;
    }
    
    /**
     * Berechnet das Layout für die Gruppenpositionierung
     * 
     * @param array $seriesByGroup Gruppierte Serien
     * @param array $chartArea Verfügbarer Zeichenbereich
     * @return array Layout-Informationen für jede Gruppe
     */
    private function calculateLayout($seriesByGroup, $chartArea) {
        $layout = [];
        $groupCount = count($seriesByGroup);
        
        // Hole den Typ des ersten Diagramms, um Layout-Entscheidungen zu treffen
        // Korrigierte Version um Notice zu vermeiden
        $firstGroup = reset($seriesByGroup);
        $firstSeriesOptions = $firstGroup ? reset($firstGroup) : [];
        
        $layoutType = isset($firstSeriesOptions['multipie']['layout']) ? 
                    $firstSeriesOptions['multipie']['layout'] : 
                    'auto';
        
        // Bestimme die Anzahl der Zeilen und Spalten für das automatische Layout
        $cols = ceil(sqrt($groupCount));
        $rows = ceil($groupCount / $cols);
        
        // Berechne die Breite und Höhe jedes Diagramms im Raster
        $cellWidth = $chartArea['width'] / $cols;
        $cellHeight = $chartArea['height'] / $rows;
        
        // Berechne für jede Gruppe die Position
        $position = 0;
        foreach ($seriesByGroup as $groupName => $groupSeries) {
            // Falls benutzerdefinierte Position für die Gruppe definiert ist
            $firstSeriesInGroup = reset($groupSeries);
            
            if (isset($firstSeriesInGroup['multipie']['position'])) {
                $customPosition = $firstSeriesInGroup['multipie']['position'];
                $layout[$groupName] = [
                    'x' => $chartArea['x'] + $customPosition['x'],
                    'y' => $chartArea['y'] + $customPosition['y'],
                    'width' => $customPosition['width'] ?? $cellWidth * 0.8,
                    'height' => $customPosition['height'] ?? $cellHeight * 0.8
                ];
            } else {
                // Automatische Positionierung im Raster
                $row = floor($position / $cols);
                $col = $position % $cols;
                
                $layout[$groupName] = [
                    'x' => $chartArea['x'] + $col * $cellWidth + $cellWidth * 0.1,
                    'y' => $chartArea['y'] + $row * $cellHeight + $cellHeight * 0.1,
                    'width' => $cellWidth * 0.8,
                    'height' => $cellHeight * 0.8
                ];
            }
            
            $position++;
        }
        
        return $layout;
    }
    
    /**
     * Rendert eine Gruppe von Pie/Donut-Diagrammen
     * 
     * @param string $groupName Name der Gruppe
     * @param array $groupSeries Serien in dieser Gruppe
     * @param array $layout Layout-Informationen
     * @param array $xValues X-Werte für alle Serien
     * @param array $yValues Y-Werte für alle Serien
     * @return string SVG-Elemente der Gruppe
     */
    private function renderGroup($groupName, $groupSeries, $layout, $xValues, $yValues) {
        $output = '';
        $groupLayout = $layout[$groupName];
        
        // Erzeuge einen eigenen Zeichenbereich für diese Gruppe
        $groupArea = [
            'x' => $groupLayout['x'],
            'y' => $groupLayout['y'],
            'width' => $groupLayout['width'],
            'height' => $groupLayout['height']
        ];
        
        // Hole den Gruppentyp und Titel
        $firstSeries = reset($groupSeries);
        $groupType = isset($firstSeries['multipie']['type']) ? 
                    $firstSeries['multipie']['type'] : 
                    'multipie'; // Standard ist MultiPie
        
        $groupTitle = isset($firstSeries['multipie']['title']) ? $firstSeries['multipie']['title'] : '';
        
        // Anpassungen für den Titel, falls vorhanden
        if (!empty($groupTitle)) {
            // Reduziere die Höhe des Zeichenbereichs für den Titel
            $titleHeight = isset($firstSeries['multipie']['titleHeight']) ? 
                         $firstSeries['multipie']['titleHeight'] : 
                         30;
            
            $groupArea['y'] += $titleHeight;
            $groupArea['height'] -= $titleHeight;
            
            // Titel-Optionen
            $titleX = $groupArea['x'] + $groupArea['width'] / 2;
            $titleY = $groupLayout['y'] + $titleHeight / 2;
            
            $titleOptions = isset($firstSeries['multipie']['titleOptions']) ? 
                          $firstSeries['multipie']['titleOptions'] : 
                          [];
            
            // Titel rendern
            $output .= $this->svg->createText(
                $titleX,
                $titleY,
                $groupTitle,
                [
                    'fontFamily' => isset($titleOptions['fontFamily']) ? $titleOptions['fontFamily'] : 'Arial, Helvetica, sans-serif',
                    'fontSize' => isset($titleOptions['fontSize']) ? $titleOptions['fontSize'] : 14,
                    'fontWeight' => isset($titleOptions['fontWeight']) ? $titleOptions['fontWeight'] : 'bold',
                    'fill' => isset($titleOptions['color']) ? $titleOptions['color'] : '#333333',
                    'textAnchor' => 'middle',
                    'dominantBaseline' => 'middle'
                ]
            );
        }
        
        // Rendere Pie oder Donut-Gruppe basierend auf dem Typ
        if (count($groupSeries) > 1) {
            // MultiPie oder MultiDonut Darstellung mit optimierter Platznutzung
            $output .= $this->renderMultiChart($groupSeries, $groupArea, $xValues, $yValues, $groupType);
        } else {
            // Ein einzelnes Diagramm in der Gruppe
            foreach ($groupSeries as $seriesName => $seriesOptions) {
                // Erstelle ein temporäres SeriesGroup mit nur dieser Serie
                $tempSeriesGroup = [$seriesName => $seriesOptions];
                
                // Rendere das Diagramm
                $output .= $this->pieChart->render(
                    $tempSeriesGroup,
                    $xValues,
                    $yValues,
                    null,
                    $groupArea,
                    $this->config
                );
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert ein Multi-Chart-Diagramm mit optimierter Platznutzung
     * 
     * @param array $groupSeries Serien in dieser Gruppe
     * @param array $groupArea Zeichenbereich für die Gruppe
     * @param array $xValues X-Werte für alle Serien
     * @param array $yValues Y-Werte für alle Serien
     * @param string $chartType Der Diagrammtyp: 'multipie' oder 'multidonut'
     * @return string SVG-Elemente des Multi-Diagramms
     */
    private function renderMultiChart($groupSeries, $groupArea, $xValues, $yValues, $chartType = 'multipie') {
        $output = '';
        
        // Bestimme die Anzahl der Ringe
        $ringCount = count($groupSeries);
        
        // Bessere Nutzung des verfügbaren Platzes - bis zu 85% der verfügbaren Fläche nutzen
        $minDimension = min($groupArea['width'], $groupArea['height']);
        $maxRadius = $minDimension * 0.85 / 2; // 85% des Platzes nutzen
        
        // Optimierter Basisabstand zwischen Ringen abhängig von der Ringanzahl
        $baseSpacing = min(5, max(2, 15 - $ringCount)); // Dynamischer Abstand
        
        // Sortiere die Serien nach Position
        $seriesCopy = $groupSeries;
        uasort($seriesCopy, function($a, $b) {
            $posA = isset($a['multipie']['ringPosition']) ? $a['multipie']['ringPosition'] : 0;
            $posB = isset($b['multipie']['ringPosition']) ? $b['multipie']['ringPosition'] : 0;
            return $posB - $posA; // Absteigend sortieren (äußerster Ring zuerst)
        });
        
        // Berechne optimale Ringdicken basierend auf Datenkomplexität
        $ringThicknesses = $this->calculateOptimalRingThicknesses($seriesCopy, $maxRadius, $chartType);
        
        // Berechne Radien basierend auf optimierten Ringdicken
        $currentRadius = $maxRadius;
        $radiusMap = [];
        
        // Ringe von außen nach innen verarbeiten
        foreach ($seriesCopy as $seriesName => $seriesOptions) {
            $relativePosition = isset($seriesOptions['multipie']['ringPosition']) ? 
                              $seriesOptions['multipie']['ringPosition'] : 0;
            
            $thickness = isset($ringThicknesses[$relativePosition]) ? 
                       $ringThicknesses[$relativePosition] : ($maxRadius / $ringCount);
                       
            $radiusMap[$seriesName] = [
                'outerRadius' => $currentRadius,
                'thickness' => $thickness
            ];
            
            // Nächster Radius unter Berücksichtigung der Lücke zwischen Ringen
            $currentRadius -= ($thickness + $baseSpacing);
        }
        
        // Rendere jeden Ring
        foreach ($seriesCopy as $seriesName => $seriesOptions) {
            // Erstelle eine Kopie der Optionen
            $options = $seriesOptions;
            
            // Bestimme Ringposition für diese Serie
            $relativePosition = isset($options['multipie']['ringPosition']) ? 
                              $options['multipie']['ringPosition'] : 0;
            
            // Hole die optimierten Radius-Informationen
            $outerRadius = $radiusMap[$seriesName]['outerRadius'];
            $ringThickness = $radiusMap[$seriesName]['thickness'];
            
            // Berechne den inneren Radius
            if ($relativePosition == 0 && $chartType === 'multipie') {
                // Innerster Ring bei MultiPie ist ein Vollkreis
                $innerRadius = 0;
            } else {
                // Bei MultiDonut oder nicht-innersten Ringen: normaler Ring
                $innerRadius = $outerRadius - $ringThickness;
            }
            
            // Aktualisiere die Optionen für diesen Ring
            $options['radius'] = $outerRadius;
            $options['innerRadius'] = $innerRadius;
            $options['centerX'] = $groupArea['x'] + $groupArea['width'] / 2;
            $options['centerY'] = $groupArea['y'] + $groupArea['height'] / 2;
            
            // Erstelle ein temporäres SeriesGroup mit nur dieser Serie
            $tempSeriesGroup = [$seriesName => $options];
            
            // Rendere ein einzelnes Pie-/Donut-Diagramm
            $output .= $this->pieChart->render(
                $tempSeriesGroup,
                $xValues,
                $yValues,
                null,
                $groupArea,
                $this->config
            );
        }
        
        return $output;
    }
    
    /**
     * Berechnet optimale Ringdicken basierend auf Datenkomplexität
     * 
     * @param array $seriesGroup Die nach Position sortierte Seriengruppe
     * @param float $maxRadius Der maximale Radius
     * @param string $chartType Der Diagrammtyp: 'multipie' oder 'multidonut'
     * @return array Array mit optimalen Ringdicken für jede Position
     */
    private function calculateOptimalRingThicknesses($seriesGroup, $maxRadius, $chartType) {
        $ringCount = count($seriesGroup);
        $thicknesses = [];
        $totalWeight = 0;
        $weights = [];
        
        // Gewicht für jeden Ring basierend auf Datenpunkten und Position berechnen
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            $position = isset($seriesOptions['multipie']['ringPosition']) ? 
                      $seriesOptions['multipie']['ringPosition'] : 0;
            
            // Ermittle die Anzahl von Datenpunkten in dieser Serie
            if (isset($seriesOptions['dataCount'])) {
                $dataCount = $seriesOptions['dataCount'];
            } else {
                // Versuche die Anzahl der Y-Werte zu bestimmen
                $seriesY = isset($seriesOptions['yValues']) ? $seriesOptions['yValues'] : [];
                $dataCount = count($seriesY) > 0 ? count($seriesY) : 4; // Standardwert
            }
            
            // Innerster Ring (vor allem für MultiPie) bekommt etwas mehr Gewicht
            $positionFactor = ($position == 0 && $chartType == 'multipie') ? 1.5 : 1.0;
            
            // Berechne Gewicht: DataCount * PositionFaktor
            $weight = $dataCount * $positionFactor;
            $weights[$position] = $weight;
            $totalWeight += $weight;
        }
        
        // Verteile den verfügbaren Platz gemäß den Gewichten
        $availableSpace = $maxRadius * 0.9; // 90% des Radius für Ringe reservieren
        
        foreach ($weights as $position => $weight) {
            $thicknesses[$position] = ($weight / $totalWeight) * $availableSpace;
        }
        
        return $thicknesses;
    }
}
?>