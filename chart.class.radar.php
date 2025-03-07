<?php
/**
 * ChartRadarChart - Radar-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Radar-Diagrammen zuständig,
 * auch bekannt als Spider- oder Netzdiagramme. Sie unterstützt sowohl Linien als auch
 * gefüllte Flächen, einzelne oder mehrere Datenreihen und Gradienten-Füllungen.
 * 
 * @version 1.0
 */
class ChartRadarChart {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
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
    }
    
    /**
     * Rendert ein Radar-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Radar-Diagramm-Serien
     * @param array $xValues Array mit X-Werten (Kategorien)
     * @param array $yValues Array mit Y-Werten (Werte)
     * @param array $axes Achsendefinitionen (nicht verwendet für Radar-Charts)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Radar-Diagramms
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
        
        // Berechne das Zentrum und den Radius des Radar-Charts
        $centerX = $chartArea['x'] + $chartArea['width'] / 2;
        $centerY = $chartArea['y'] + $chartArea['height'] / 2;
        $radius = min($chartArea['width'], $chartArea['height']) / 2 * 0.85; // 85% des verfügbaren Platzes
        
        // Kategorien (Achsen) ermitteln
        $categories = $this->getCategories($xValues);
        $categoryCount = count($categories);
        
        // Keine Kategorien? Nichts zu rendern
        if ($categoryCount === 0) {
            return '';
        }
        
        // Finde den maximalen Wert für die Skalierung
        $maxValue = $this->findMaxValue($yValues);
        
        // Rendere das Radar-Gitter
        $radarGridOptions = isset($config['radar']) && isset($config['radar']['grid']) ? 
                          $config['radar']['grid'] : [];
        $output .= $this->renderRadarGrid($centerX, $centerY, $radius, $categoryCount, $radarGridOptions);
        
        // Rendere Kategorie-Achsen und Beschriftungen
        $radarAxesOptions = isset($config['radar']) && isset($config['radar']['axes']) ? 
                          $config['radar']['axes'] : [];
        $output .= $this->renderRadarAxes($centerX, $centerY, $radius, $categories, $radarAxesOptions);
        
        // Bestimme, ob es gestapelte Radar-Diagramme gibt
        $hasStacked = false;
        foreach ($updatedSeriesGroup as $seriesOptions) {
            if (isset($seriesOptions['stacked']) && $seriesOptions['stacked']) {
                $hasStacked = true;
                break;
            }
        }
        
        // Sammle stacked Series Gruppen und unstacked Series
        $stackedGroups = [];
        $unStackedSeries = [];
        
        foreach ($updatedSeriesGroup as $seriesName => $seriesOptions) {
            if (isset($seriesOptions['stacked']) && $seriesOptions['stacked']) {
                $stackGroup = isset($seriesOptions['stackGroup']) ? $seriesOptions['stackGroup'] : 'default';
                if (!isset($stackedGroups[$stackGroup])) {
                    $stackedGroups[$stackGroup] = [];
                }
                $stackedGroups[$stackGroup][$seriesName] = $seriesOptions;
            } else {
                $unStackedSeries[$seriesName] = $seriesOptions;
            }
        }
        
        // Rendere gestapelte Serien
        foreach ($stackedGroups as $stackGroup => $stackedSeries) {
            $output .= $this->renderStackedRadar(
                $stackedSeries,
                $xValues,
                $yValues,
                $centerX,
                $centerY,
                $radius,
                $maxValue,
                $categories,
                $config
            );
        }
        
        // Rendere nicht gestapelte Serien
        foreach ($unStackedSeries as $seriesName => $seriesOptions) {
            $output .= $this->renderRadarSeries(
                $seriesName,
                $seriesOptions,
                $xValues,
                $yValues,
                $centerX,
                $centerY,
                $radius,
                $maxValue,
                $categories,
                $config
            );
        }
        
        return $output;
    }
    
    /**
     * Erstellt Gradienten für alle Serien, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Radar-Diagramm-Serien
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
     * Ermittelt alle Kategorien (Achsen) für das Radar-Diagramm
     * 
     * @param array $xValues Array mit X-Werten (Kategorien)
     * @return array Array mit allen eindeutigen Kategorien
     */
    private function getCategories($xValues) {
        $categories = [];
        
        // Wenn default X-Werte vorhanden sind, verwende diese
        if (isset($xValues['default']) && !empty($xValues['default'])) {
            return $xValues['default'];
        }
        
        // Andernfalls sammle alle eindeutigen Kategorien aus allen Serien
        foreach ($xValues as $seriesName => $seriesX) {
            if ($seriesName === 'default') continue;
            
            foreach ($seriesX as $category) {
                if (!in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * Findet den maximalen Y-Wert über alle Serien
     * 
     * @param array $yValues Array mit Y-Werten
     * @return float Maximaler Y-Wert
     */
    private function findMaxValue($yValues) {
        $maxValue = 0;
        
        foreach ($yValues as $seriesName => $seriesY) {
            foreach ($seriesY as $value) {
                if (is_numeric($value) && $value > $maxValue) {
                    $maxValue = $value;
                }
            }
        }
        
        // Mindestens 1, um Division durch Null zu vermeiden
        return max(1, $maxValue);
    }
    
    /**
     * Rendert das Radar-Gitter (konzentrische Kreise und Achsenlinien)
     * 
     * @param float $centerX X-Koordinate des Zentrums
     * @param float $centerY Y-Koordinate des Zentrums
     * @param float $radius Radius des Radar-Diagramms
     * @param int $categoryCount Anzahl der Kategorien/Achsen
     * @param array $options Optionen für das Gitter
     * @return string SVG-Elemente des Radar-Gitters
     */
    private function renderRadarGrid($centerX, $centerY, $radius, $categoryCount, $options = []) {
        $output = '';
        
        // Standardoptionen für das Gitter
        $gridEnabled = isset($options['enabled']) ? $options['enabled'] : true;
        if (!$gridEnabled) {
            return '';
        }
        
        $gridColor = isset($options['color']) ? $options['color'] : '#e0e0e0';
        $gridWidth = isset($options['width']) ? $options['width'] : 1;
        $gridDashArray = isset($options['dashArray']) ? $options['dashArray'] : '';
        $gridLevels = isset($options['levels']) ? $options['levels'] : 5;
        
        // Konzentrische Kreise rendern
        for ($i = 1; $i <= $gridLevels; $i++) {
            $levelRadius = $radius * ($i / $gridLevels);
            
            $output .= $this->svg->createCircle(
                $centerX,
                $centerY,
                $levelRadius,
                [
                    'fill' => 'none',
                    'stroke' => $gridColor,
                    'strokeWidth' => $gridWidth,
                    'strokeDasharray' => $gridDashArray
                ]
            );
        }
        
        // Achsenlinien rendern
        for ($i = 0; $i < $categoryCount; $i++) {
            $angle = (2 * M_PI * $i / $categoryCount) - M_PI / 2; // Start bei 12 Uhr
            $endX = $centerX + $radius * cos($angle);
            $endY = $centerY + $radius * sin($angle);
            
            $output .= $this->svg->createLine(
                $centerX,
                $centerY,
                $endX,
                $endY,
                [
                    'stroke' => $gridColor,
                    'strokeWidth' => $gridWidth,
                    'strokeDasharray' => $gridDashArray
                ]
            );
        }
        
        return $output;
    }
    
    /**
     * Rendert die Radar-Achsen und Beschriftungen
     * 
     * @param float $centerX X-Koordinate des Zentrums
     * @param float $centerY Y-Koordinate des Zentrums
     * @param float $radius Radius des Radar-Diagramms
     * @param array $categories Array mit Kategorienamen
     * @param array $options Optionen für die Achsen
     * @return string SVG-Elemente der Radar-Achsen
     */
    private function renderRadarAxes($centerX, $centerY, $radius, $categories, $options = []) {
        $output = '';
        
        // Standardoptionen für die Achsen
        $labelsEnabled = isset($options['labels']) && isset($options['labels']['enabled']) ? 
                        $options['labels']['enabled'] : true;
        
        if (!$labelsEnabled) {
            return '';
        }
        
        $labelColor = isset($options['labels']) && isset($options['labels']['color']) ? 
                     $options['labels']['color'] : '#333333';
        $labelFontSize = isset($options['labels']) && isset($options['labels']['fontSize']) ? 
                        $options['labels']['fontSize'] : 12;
        $labelFontFamily = isset($options['labels']) && isset($options['labels']['fontFamily']) ? 
                          $options['labels']['fontFamily'] : 'Arial, Helvetica, sans-serif';
        $labelOffset = isset($options['labels']) && isset($options['labels']['offset']) ? 
                      $options['labels']['offset'] : 10;
        
        // Kategorie-Beschriftungen rendern
        $categoryCount = count($categories);
        for ($i = 0; $i < $categoryCount; $i++) {
            $angle = (2 * M_PI * $i / $categoryCount) - M_PI / 2; // Start bei 12 Uhr
            
            // Position für das Label
            $labelRadius = $radius + $labelOffset;
            $labelX = $centerX + $labelRadius * cos($angle);
            $labelY = $centerY + $labelRadius * sin($angle);
            
            // Textausrichtung basierend auf Position
            $textAnchor = 'middle';
            if ($labelX < $centerX - $radius * 0.1) {
                $textAnchor = 'end';
            } else if ($labelX > $centerX + $radius * 0.1) {
                $textAnchor = 'start';
            }
            
            // Vertikale Ausrichtung
            $dominantBaseline = 'middle';
            if ($labelY < $centerY - $radius * 0.1) {
                $dominantBaseline = 'baseline';
            } else if ($labelY > $centerY + $radius * 0.1) {
                $dominantBaseline = 'hanging';
            }
            
            // Label rendern
            $output .= $this->svg->createText(
                $labelX,
                $labelY,
                $categories[$i],
                [
                    'fontFamily' => $labelFontFamily,
                    'fontSize' => $labelFontSize,
                    'fill' => $labelColor,
                    'textAnchor' => $textAnchor,
                    'dominantBaseline' => $dominantBaseline
                ]
            );
        }
        
        return $output;
    }
    
    /**
     * Rendert eine einzelne Radar-Diagramm-Serie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param float $centerX X-Koordinate des Zentrums
     * @param float $centerY Y-Koordinate des Zentrums
     * @param float $radius Radius des Radar-Diagramms
     * @param float $maxValue Maximaler Y-Wert für die Skalierung
     * @param array $categories Array mit Kategorienamen
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente der Radar-Serie
     */
    private function renderRadarSeries($seriesName, $seriesOptions, $xValues, $yValues, $centerX, $centerY, $radius, $maxValue, $categories, $config) {
        $output = '';
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine Werte vorhanden sind, nichts rendern
        if (empty($seriesY)) {
            return '';
        }
        
        // Optionen für die Fläche
        $showArea = isset($seriesOptions['area']) && isset($seriesOptions['area']['enabled']) ? 
                   $seriesOptions['area']['enabled'] : true;
        $areaOpacity = isset($seriesOptions['area']) && isset($seriesOptions['area']['fillOpacity']) ? 
                      $seriesOptions['area']['fillOpacity'] : 0.4;
        
        // Farbe und Gradienten
        $color = isset($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        $fillColor = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $color;
        
        // Linie optionen
        $lineWidth = isset($seriesOptions['line']) && isset($seriesOptions['line']['width']) ? 
                    $seriesOptions['line']['width'] : 2;
        $lineDashArray = isset($seriesOptions['line']) && isset($seriesOptions['line']['dashArray']) ? 
                        $seriesOptions['line']['dashArray'] : '';
        
        // Sammle die Punkte für das Radar-Diagramm
        $points = [];
        $categoryCount = count($categories);
        
        for ($i = 0; $i < $categoryCount; $i++) {
            $category = $categories[$i];
            
            // Finde den Y-Wert für diese Kategorie
            $value = 0;
            foreach ($seriesX as $idx => $xCategory) {
                if ($xCategory === $category && isset($seriesY[$idx])) {
                    $value = $seriesY[$idx];
                    break;
                }
            }
            
            // Berechne den Punkt auf dem Radar-Diagramm
            $scaledRadius = ($value / $maxValue) * $radius;
            $angle = (2 * M_PI * $i / $categoryCount) - M_PI / 2; // Start bei 12 Uhr
            
            $x = $centerX + $scaledRadius * cos($angle);
            $y = $centerY + $scaledRadius * sin($angle);
            
            $points[] = [$x, $y];
        }
        
        // Den letzten Punkt dem ersten gleichsetzen, um das Polygon zu schließen
        if (!empty($points)) {
            $points[] = $points[0];
        }
        
        // Rendern der Fläche, wenn aktiviert
        if ($showArea) {
            $polygonPoints = [];
            foreach ($points as $point) {
                $polygonPoints[] = [$point[0], $point[1]];
            }
            
            $output .= $this->svg->createPolygon(
                $polygonPoints,
                [
                    'fill' => $fillColor,
                    'fillOpacity' => $areaOpacity,
                    'stroke' => 'none'
                ]
            );
        }
        
        // Rendern der Linie
        $polylinePoints = [];
        foreach ($points as $point) {
            $polylinePoints[] = [$point[0], $point[1]];
        }
        
        $output .= $this->svg->createPolyline(
            $polylinePoints,
            [
                'fill' => 'none',
                'stroke' => $color,
                'strokeWidth' => $lineWidth,
                'strokeDasharray' => $lineDashArray
            ]
        );
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            foreach ($points as $idx => $point) {
                // Den letzten Punkt (Schließpunkt) nicht doppelt rendern
                if ($idx === count($points) - 1) {
                    continue;
                }
                
                $pointSize = isset($seriesOptions['point']['size']) ? $seriesOptions['point']['size'] : 5;
                $pointColor = isset($seriesOptions['point']['color']) && $seriesOptions['point']['color'] ? 
                             $seriesOptions['point']['color'] : $color;
                $pointShape = isset($seriesOptions['point']['shape']) ? $seriesOptions['point']['shape'] : 'circle';
                
                $this->renderPoint($point[0], $point[1], $pointSize, $pointColor, $pointShape, $output);
            }
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $this->renderDataLabels($points, $seriesY, $seriesOptions, $output);
        }
        
        return $output;
    }
    
    /**
     * Rendert gestapelte Radar-Diagramme
     * 
     * @param array $stackedSeries Array mit gestapelten Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param float $centerX X-Koordinate des Zentrums
     * @param float $centerY Y-Koordinate des Zentrums
     * @param float $radius Radius des Radar-Diagramms
     * @param float $maxValue Maximaler Y-Wert für die Skalierung
     * @param array $categories Array mit Kategorienamen
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente der gestapelten Radar-Serien
     */
    private function renderStackedRadar($stackedSeries, $xValues, $yValues, $centerX, $centerY, $radius, $maxValue, $categories, $config) {
        $output = '';
        
        // Berechne die gestapelten Werte für jede Kategorie
        $stackedValues = array_fill(0, count($categories), 0);
        $categoryCount = count($categories);
        
        // Renderreihenfolge von hinten nach vorne (erste Serie zuoberst)
        $reversedSeries = array_reverse($stackedSeries, true);
        
        foreach ($reversedSeries as $seriesName => $seriesOptions) {
            $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
            $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
            
            // Optionen für die Fläche
            $showArea = isset($seriesOptions['area']) && isset($seriesOptions['area']['enabled']) ? 
                       $seriesOptions['area']['enabled'] : true;
            $areaOpacity = isset($seriesOptions['area']) && isset($seriesOptions['area']['fillOpacity']) ? 
                          $seriesOptions['area']['fillOpacity'] : 0.4;
            
            // Farbe und Gradienten
            $color = isset($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
            $fillColor = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $color;
            
            // Linie optionen
            $lineWidth = isset($seriesOptions['line']) && isset($seriesOptions['line']['width']) ? 
                        $seriesOptions['line']['width'] : 2;
            $lineDashArray = isset($seriesOptions['line']) && isset($seriesOptions['line']['dashArray']) ? 
                            $seriesOptions['line']['dashArray'] : '';
            
            // Punkte für das aktuelle gestapelte Radar-Diagramm
            $topPoints = [];
            $bottomPoints = [];
            
            for ($i = 0; $i < $categoryCount; $i++) {
                $category = $categories[$i];
                
                // Finde den Y-Wert für diese Kategorie
                $value = 0;
                foreach ($seriesX as $idx => $xCategory) {
                    if ($xCategory === $category && isset($seriesY[$idx])) {
                        $value = max(0, $seriesY[$idx]); // Nur positive Werte stapeln
                        break;
                    }
                }
                
                // Berechne die Punkte für oben und unten der Fläche
                $stackBase = $stackedValues[$i];
                $stackEnd = $stackBase + $value;
                $stackedValues[$i] = $stackEnd; // Aktualisiere den Stapelwert für die nächste Serie
                
                // Berechne den Punkt auf dem Radar-Diagramm für den oberen Teil (aktueller Stapelwert)
                $topRadius = ($stackEnd / $maxValue) * $radius;
                $angle = (2 * M_PI * $i / $categoryCount) - M_PI / 2; // Start bei 12 Uhr
                
                $topX = $centerX + $topRadius * cos($angle);
                $topY = $centerY + $topRadius * sin($angle);
                
                $topPoints[] = [$topX, $topY];
                
                // Berechne den Punkt auf dem Radar-Diagramm für den unteren Teil (vorheriger Stapelwert)
                $bottomRadius = ($stackBase / $maxValue) * $radius;
                $bottomX = $centerX + $bottomRadius * cos($angle);
                $bottomY = $centerY + $bottomRadius * sin($angle);
                
                $bottomPoints[] = [$bottomX, $bottomY];
            }
            
            // Den letzten Punkt dem ersten gleichsetzen, um das Polygon zu schließen
            if (!empty($topPoints)) {
                $topPoints[] = $topPoints[0];
                $bottomPoints[] = $bottomPoints[0];
            }
            
            // Für gestapelte Radar-Diagramme müssen wir einen speziellen Pfad erstellen,
            // der die oberen und unteren Punkte verbindet
            if (!empty($topPoints) && !empty($bottomPoints)) {
                $pathData = 'M' . $topPoints[0][0] . ',' . $topPoints[0][1];
                
                // Obere Linie von links nach rechts
                for ($i = 1; $i < count($topPoints); $i++) {
                    $pathData .= ' L' . $topPoints[$i][0] . ',' . $topPoints[$i][1];
                }
                
                // Untere Linie von rechts nach links
                for ($i = count($bottomPoints) - 1; $i >= 0; $i--) {
                    $pathData .= ' L' . $bottomPoints[$i][0] . ',' . $bottomPoints[$i][1];
                }
                
                // Pfad schließen
                $pathData .= ' Z';
                
                // Fläche rendern
                if ($showArea) {
                    $output .= $this->svg->createPath(
                        $pathData,
                        [
                            'fill' => $fillColor,
                            'fillOpacity' => $areaOpacity,
                            'stroke' => 'none'
                        ]
                    );
                }
                
                // Obere Linie rendern
                $output .= $this->svg->createPolyline(
                    $topPoints,
                    [
                        'fill' => 'none',
                        'stroke' => $color,
                        'strokeWidth' => $lineWidth,
                        'strokeDasharray' => $lineDashArray
                    ]
                );
                
                // Punkte rendern, falls aktiviert
                if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
                    foreach ($topPoints as $idx => $point) {
                        // Den letzten Punkt (Schließpunkt) nicht doppelt rendern
                        if ($idx === count($topPoints) - 1) {
                            continue;
                        }
                        
                        $pointSize = isset($seriesOptions['point']['size']) ? $seriesOptions['point']['size'] : 5;
                        $pointColor = isset($seriesOptions['point']['color']) && $seriesOptions['point']['color'] ? 
                                     $seriesOptions['point']['color'] : $color;
                        $pointShape = isset($seriesOptions['point']['shape']) ? $seriesOptions['point']['shape'] : 'circle';
                        
                        $this->renderPoint($point[0], $point[1], $pointSize, $pointColor, $pointShape, $output);
                    }
                }
                
                // Datenwertbeschriftungen rendern, falls aktiviert
                if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                    $this->renderDataLabels($topPoints, $seriesY, $seriesOptions, $output);
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert einen einzelnen Punkt
     * 
     * @param float $x X-Koordinate
     * @param float $y Y-Koordinate
     * @param float $size Größe des Punkts
     * @param string $color Farbe des Punkts
     * @param string $shape Form des Punkts (circle, square, triangle, diamond)
     * @param string &$output Referenz auf die SVG-Ausgabe
     */
    private function renderPoint($x, $y, $size, $color, $shape, &$output) {
        switch ($shape) {
            case 'circle':
                $output .= $this->svg->createCircle(
                    $x,
                    $y,
                    $size / 2,
                    [
                        'fill' => $color,
                        'stroke' => 'none'
                    ]
                );
                break;
                
            case 'square':
                $output .= $this->svg->createRect(
                    $x - $size / 2,
                    $y - $size / 2,
                    $size,
                    $size,
                    [
                        'fill' => $color,
                        'stroke' => 'none'
                    ]
                );
                break;
                
            case 'triangle':
                $points = [
                    [$x, $y - $size / 2],
                    [$x - $size / 2, $y + $size / 2],
                    [$x + $size / 2, $y + $size / 2]
                ];
                
                $output .= $this->svg->createPolygon(
                    $points,
                    [
                        'fill' => $color,
                        'stroke' => 'none'
                    ]
                );
                break;
                
            case 'diamond':
                $points = [
                    [$x, $y - $size / 2],
                    [$x + $size / 2, $y],
                    [$x, $y + $size / 2],
                    [$x - $size / 2, $y]
                ];
                
                $output .= $this->svg->createPolygon(
                    $points,
                    [
                        'fill' => $color,
                        'stroke' => 'none'
                    ]
                );
                break;
                
            default:
                // Standardmäßig Kreis
                $output .= $this->svg->createCircle(
                    $x,
                    $y,
                    $size / 2,
                    [
                        'fill' => $color,
                        'stroke' => 'none'
                    ]
                );
                break;
        }
    }
    
    /**
     * Rendert Datenwertbeschriftungen für die Punkte
     * 
     * @param array $points Array mit Punkten
     * @param array $values Array mit den Werten
     * @param array $seriesOptions Optionen für die Serie
     * @param string &$output Referenz auf die SVG-Ausgabe
     */
    private function renderDataLabels($points, $values, $seriesOptions, &$output) {
        $labelOptions = $seriesOptions['dataLabels'];
        $labelFormat = isset($labelOptions['format']) ? $labelOptions['format'] : '{y}';
        $labelColor = isset($labelOptions['color']) ? $labelOptions['color'] : '#333333';
        $labelFontSize = isset($labelOptions['fontSize']) ? $labelOptions['fontSize'] : 12;
        $labelFontFamily = isset($labelOptions['fontFamily']) ? $labelOptions['fontFamily'] : 'Arial, Helvetica, sans-serif';
        $labelOffsetX = isset($labelOptions['offsetX']) ? $labelOptions['offsetX'] : 0;
        $labelOffsetY = isset($labelOptions['offsetY']) ? $labelOptions['offsetY'] : -10;
        
        for ($i = 0; $i < count($points) - 1; $i++) { // -1 weil der letzte Punkt ein Schließpunkt ist
            $point = $points[$i];
            $value = isset($values[$i]) ? $values[$i] : 0;
            
            // Label-Text formatieren
            $labelText = str_replace('{y}', $this->utils->formatNumber($value), $labelFormat);
            
            $output .= $this->svg->createText(
                $point[0] + $labelOffsetX,
                $point[1] + $labelOffsetY,
                $labelText,
                [
                    'fontFamily' => $labelFontFamily,
                    'fontSize' => $labelFontSize,
                    'fill' => $labelColor,
                    'textAnchor' => 'middle'
                ]
            );
        }
    }
}
?>