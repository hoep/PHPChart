<?php
/**
 * ChartBarChart - Balkendiagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Balkendiagrammen zuständig,
 * einschließlich vertikaler und horizontaler Balken sowie gestapelter Varianten.
 * 
 * @version 2.7
 */
class ChartBarChart {
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
     * Rendert ein Balkendiagramm
     * 
     * @param array $seriesGroup Gruppe von Balkendiagramm-Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Balkendiagramms
     */
    public function render($seriesGroup, $xValues, $yValues, $axes, $chartArea, $config) {
        // Prüfen, ob es horizontale Balken gibt
        $hasHorizontalBars = false;
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            if (isset($seriesOptions['bar']) && isset($seriesOptions['bar']['horizontal']) && $seriesOptions['bar']['horizontal']) {
                $hasHorizontalBars = true;
                break;
            }
        }
        
        // Setze das Flag für horizontale Balken in der Achsenklasse
        $this->axes->setHorizontalBars($hasHorizontalBars);
        
        // Erstelle Gradienten für alle Serien, die diese benötigen
        $this->prepareGradients($seriesGroup, $hasHorizontalBars);
        
        // Initialisiere Ausgabe
        $output = '';
        
        // Erzeuge Defs-Sektion für Gradienten
        $defs = $this->generateGradientDefsSection();
        if (!empty($defs)) {
            $output .= $this->svg->createDefs($defs);
        }
        
        // Aktualisiere die Serien mit Gradienten-IDs
        $updatedSeriesGroup = $this->applyGradientIds($seriesGroup);
        
        // Gruppiere Serien nach Stapelgruppe und Achsen
        $seriesByStack = $this->groupSeriesByStack($updatedSeriesGroup);
        
        // Zähle die Stapelgruppen (für die Verteilung der Balken in einer Kategorie)
        $stackGroups = array_keys($seriesByStack);
        $stackGroupCount = count($stackGroups);
        
        // Rendere Balken für jede Stapelgruppe
        $stackGroupIndex = 0;
        foreach ($seriesByStack as $stackGroup => $stackedSeries) {
            // Prüfe, ob gestapelt oder nicht
            $isStacked = ($stackGroup !== 'unstacked');
            
            // Sammle Serien für diese Gruppe
            $seriesInGroup = [];
            foreach ($stackedSeries as $axisKey => $axisGroup) {
                foreach ($axisGroup as $seriesName => $seriesOptions) {
                    $seriesInGroup[$seriesName] = $seriesOptions;
                }
            }
            
            // Bestimme, ob horizontale Balken gerendert werden sollen
            $horizontal = false;
            foreach ($seriesInGroup as $seriesOptions) {
                if (isset($seriesOptions['bar']) && isset($seriesOptions['bar']['horizontal']) && $seriesOptions['bar']['horizontal']) {
                    $horizontal = true;
                    break;
                }
            }
            
            if ($horizontal) {
                // Horizontale Balken rendern
                if ($isStacked) {
                    $output .= $this->renderHorizontalStackedBars($seriesInGroup, $xValues, $yValues, $axes, $chartArea, $stackGroupIndex, $stackGroupCount);
                } else {
                    $output .= $this->renderHorizontalUnstackedBars($seriesInGroup, $xValues, $yValues, $axes, $chartArea, $stackGroupIndex, $stackGroupCount);
                }
            } else {
                // Vertikale Balken rendern
                if ($isStacked) {
                    $output .= $this->renderVerticalStackedBars($seriesInGroup, $xValues, $yValues, $axes, $chartArea, $stackGroupIndex, $stackGroupCount);
                } else {
                    $output .= $this->renderVerticalUnstackedBars($seriesInGroup, $xValues, $yValues, $axes, $chartArea);
                }
            }
            
            $stackGroupIndex++;
        }
        
        return $output;
    }
    
    /**
     * Erstellt Gradienten für alle Serien, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Balkendiagramm-Serien
     * @param bool $horizontal Ob horizontale Balken gerendert werden
     */
    private function prepareGradients($seriesGroup, $horizontal = false) {
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            if (isset($seriesOptions['gradient']) && isset($seriesOptions['gradient']['enabled']) && $seriesOptions['gradient']['enabled']) {
                // Generiere eine sichere ID ohne Leerzeichen oder ungültige Zeichen
                $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                $gradientId = 'gradient_' . $safeSeriesName . '_' . $this->utils->generateId();
                
                // Speichere Gradientendefinition im Cache
                $this->gradientCache[$seriesName] = [
                    'id' => $gradientId,
                    'options' => $seriesOptions['gradient'],
                    'horizontal' => $horizontal,
                    'color' => $seriesOptions['color']
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
        
        foreach ($this->gradientCache as $seriesName => $gradientInfo) {
            $gradientId = $gradientInfo['id'];
            $gradientOptions = $gradientInfo['options'];
            $horizontal = $gradientInfo['horizontal'];
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
            
            // Bestimme Default-Winkel je nach Balkenorientierung
            $defaultAngle = $horizontal ? 0 : 90;
            
            if ($type === 'linear') {
                $angle = isset($gradientOptions['angle']) ? $gradientOptions['angle'] : $defaultAngle;
                
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
        
        foreach ($this->gradientCache as $seriesName => $gradientInfo) {
            if (isset($updatedSeriesGroup[$seriesName])) {
                $updatedSeriesGroup[$seriesName]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
            }
        }
        
        return $updatedSeriesGroup;
    }
    
    /**
     * Gruppiert Serien nach Stapelgruppe und Achsen
     * 
     * @param array $seriesGroup Gruppe von Balkendiagramm-Serien
     * @return array Gruppierte Serien
     */
    private function groupSeriesByStack($seriesGroup) {
        $seriesByStack = [];
        
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            $isStacked = isset($seriesOptions['stacked']) ? $seriesOptions['stacked'] : false;
            $stackGroup = $isStacked ? (isset($seriesOptions['stackGroup']) ? $seriesOptions['stackGroup'] : 'default') : 'unstacked';
            $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
            $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
            $axisKey = $xAxisId . '_' . $yAxisId;
            
            if (!isset($seriesByStack[$stackGroup])) {
                $seriesByStack[$stackGroup] = [];
            }
            
            if (!isset($seriesByStack[$stackGroup][$axisKey])) {
                $seriesByStack[$stackGroup][$axisKey] = [];
            }
            
            $seriesByStack[$stackGroup][$axisKey][$seriesName] = $seriesOptions;
        }
        
        return $seriesByStack;
    }
    
    /**
     * Rendert horizontale gestapelte Balken
     * 
     * @param array $series Array mit Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param int $stackGroupIndex Index der Stapelgruppe
     * @param int $stackGroupCount Anzahl der Stapelgruppen
     * @return string SVG-Elemente der horizontalen gestapelten Balken
     */
    private function renderHorizontalStackedBars($series, $xValues, $yValues, $axes, $chartArea, $stackGroupIndex, $stackGroupCount) {
        $output = '';
        
        // Bestimme die zu verwendenden Achsen (erste Serie)
        $firstSeries = reset($series);
        $xAxisId = isset($firstSeries['xAxisId']) ? $firstSeries['xAxisId'] : 0;
        $yAxisId = isset($firstSeries['yAxisId']) ? $firstSeries['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Bei horizontalen Balken sind Y-Werte die Kategorien
        // Wir prüfen zunächst, ob Kategorien direkt in der Y-Achse angegeben sind
        if (isset($yAxis['categories']) && !empty($yAxis['categories'])) {
            $categories = $yAxis['categories'];
        } else {
            // Falls keine Kategorien direkt angegeben sind, versuche aus den Y-Werten zu ermitteln
            $allCategories = [];
            foreach ($yValues as $seriesY) {
                foreach ($seriesY as $category) {
                    if (!in_array($category, $allCategories) && !empty($category)) {
                        $allCategories[] = $category;
                    }
                }
            }
            
            if (!empty($allCategories)) {
                $categories = $allCategories;
            } else {
                // Fallback: Bestimme die maximale Anzahl von Werten in einer Serie und erstelle
                // entsprechend viele Standardkategorien
                $maxCount = 0;
                foreach ($yValues as $seriesY) {
                    $maxCount = max($maxCount, count($seriesY));
                }
                
                $categories = [];
                for ($i = 0; $i < $maxCount; $i++) {
                    $categories[] = "Kategorie " . ($i + 1);
                }
            }
        }
        
        // Sicherheitscheck - falls keine Kategorien da sind
        if (empty($categories)) {
            return $output;
        }
        
        // Berechne Höhenparameter basierend auf der Anzahl der Kategorien
        $categoryCount = count($categories);
        $categoryHeight = $chartArea['height'] / $categoryCount;
        
        // Verfügbarer Platz pro Kategorie (80% der Kategoriehöhe)
        $availableHeight = $categoryHeight * 0.8;
        
        // Höhe für jede Stapelgruppe
        $stackGroupHeight = $availableHeight / $stackGroupCount;
        
        // Zwischenraum zwischen Stapelgruppen (10% der Stapelgruppenhöhe)
        $stackGroupSpacing = $stackGroupHeight * 0.1;
        
        // Tatsächliche Balkenhöhe (90% der Stapelgruppenhöhe)
        $barHeight = $stackGroupHeight - $stackGroupSpacing;
        
        // Überprüfe benutzerdefinierte Höhe
        if (isset($firstSeries['bar']) && isset($firstSeries['bar']['width']) && $firstSeries['bar']['width'] !== null) {
            $barHeight = min($firstSeries['bar']['width'], $barHeight);
        }
        
        // Finde die Position der Nulllinie auf der X-Achse
        $zeroX = $this->axes->convertXValueToCoordinate(0, $xAxis, $chartArea);
        
        // Berechne die Stapel für jede Kategorie
        $stacks = [];
        foreach ($series as $seriesName => $seriesOptions) {
            // X-Werte holen (repräsentieren die Balkenlängen)
            $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : [];
            
            // Y-Werte holen (repräsentieren die Kategorien)
            $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
            
            // Für jede Kategorie in den Y-Werten
            foreach ($seriesY as $idx => $category) {
                // Wenn keine entsprechenden X-Wert vorhanden ist, überspringe
                if (!isset($seriesX[$idx])) continue;
                
                // Finde den Index der Kategorie im categories-Array
                $categoryIndex = array_search($category, $categories);
                if ($categoryIndex === false) continue;
                
                // X-Wert für diese Kategorie (Balkenlänge)
                $value = $seriesX[$idx];
                if (!is_numeric($value)) continue;
                
                // Initialisiere den Stack für diese Kategorie falls noch nicht vorhanden
                if (!isset($stacks[$categoryIndex])) {
                    $stacks[$categoryIndex] = [
                        'positive' => 0,  // Summe positiver Werte
                        'negative' => 0,  // Summe negativer Werte
                        'items' => []
                    ];
                }
                
                // Negative und positive Werte separat stapeln
                if ($value >= 0) {
                    $stackBase = $stacks[$categoryIndex]['positive'];
                    $stackEnd = $stackBase + $value;
                    $stacks[$categoryIndex]['positive'] = $stackEnd;
                } else {
                    $stackBase = $stacks[$categoryIndex]['negative'];
                    $stackEnd = $stackBase + $value;
                    $stacks[$categoryIndex]['negative'] = $stackEnd;
                }
                
                $stacks[$categoryIndex]['items'][] = [
                    'seriesName' => $seriesName,
                    'value' => $value,
                    'stackBase' => $stackBase,
                    'stackEnd' => $stackEnd
                ];
            }
        }
        
        // Rendere jeden Stapel
        foreach ($stacks as $categoryIndex => $stack) {
            // Überprüfe, ob der Index im gültigen Bereich liegt
            if ($categoryIndex >= $categoryCount) continue;
            
            // Berechne die Mitte der Kategorie
            $categoryCenter = $chartArea['y'] + ($categoryIndex + 0.5) * $categoryHeight;
            
            // Berechne die Y-Position für die aktuelle Stapelgruppe
            // Beginne oben in der Kategorie und verteile die Stapelgruppen von oben nach unten
            $y = $categoryCenter - ($availableHeight / 2) + ($stackGroupIndex * $stackGroupHeight) + ($stackGroupSpacing / 2);
            
            // Rendere jeden Balken im Stapel
            foreach ($stack['items'] as $item) {
                $seriesName = $item['seriesName'];
                $seriesOptions = $series[$seriesName];
                $value = $item['value'];
                $stackBase = $item['stackBase'];
                $stackEnd = $item['stackEnd'];
                
                // X-Positionen für den Balken
                $x1 = $this->axes->convertXValueToCoordinate($stackBase, $xAxis, $chartArea);
                $x2 = $this->axes->convertXValueToCoordinate($stackEnd, $xAxis, $chartArea);
                
                // Stelle sicher, dass x1 < x2
                if ($x1 > $x2) {
                    list($x1, $x2) = [$x2, $x1];
                }
                
                // Balkenbreite berechnen
                $barWidth = abs($x2 - $x1);
                
                // Mindestbreite für sichtbare Balken
                if ($barWidth < 1) {
                    $barWidth = 1;
                }
                
                // Farbe bestimmen
                $color = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
                
                // Gradient-ID verwenden, falls vorhanden
                $fillColor = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $color;
                
                // Eckenradius anwenden
                $cornerRadius = isset($seriesOptions['bar']) && isset($seriesOptions['bar']['cornerRadius']) ?
                                $seriesOptions['bar']['cornerRadius'] : 0;
                
                // Balken rendern
                $output .= $this->svg->createRect(
                    $x1,
                    $y,
                    $barWidth,
                    $barHeight,
                    [
                        'fill' => $fillColor,
                        'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                        'rx' => $cornerRadius,
                        'ry' => $cornerRadius
                    ]
                );
                
                // Datenwertbeschriftung rendern, falls aktiviert
                if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                    $labelX = $value >= 0 ? $x2 + 5 : $x1 - 5;
                    $textAnchor = $value >= 0 ? 'start' : 'end';
                    
                    $output .= $this->renderDataLabel(
                        $labelX,
                        $y + $barHeight / 2,
                        $value,
                        $seriesOptions['dataLabels'],
                        $textAnchor
                    );
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert horizontale nicht-gestapelte Balken
     * 
     * @param array $series Array mit Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param int $stackGroupIndex Index der Stapelgruppe (für Positionierung)
     * @param int $stackGroupCount Anzahl der Stapelgruppen
     * @return string SVG-Elemente der horizontalen nicht-gestapelten Balken
     */
    private function renderHorizontalUnstackedBars($series, $xValues, $yValues, $axes, $chartArea, $stackGroupIndex = 0, $stackGroupCount = 1) {
        $output = '';
        
        // Anzahl der Serien
        $seriesCount = count($series);
        
        // Bestimme die zu verwendenden Achsen (erste Serie)
        $firstSeries = reset($series);
        $xAxisId = isset($firstSeries['xAxisId']) ? $firstSeries['xAxisId'] : 0;
        $yAxisId = isset($firstSeries['yAxisId']) ? $firstSeries['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die Kategorien von der Y-Achse
        $categories = isset($yAxis['categories']) ? $yAxis['categories'] : [];
        $categoryCount = count($categories);
        
        if (empty($categories)) {
            // Wenn keine Kategorien definiert sind, nichts zu tun
            return $output;
        }
        
        // Berechne die Kategoriehöhe
        $categoryHeight = $chartArea['height'] / $categoryCount;
        
        // Balkenhöhe für alle Serien in einer Kategorie (80% der Kategoriehöhe)
        $barGroupHeight = $categoryHeight * 0.8;
        
        // Höhe für einen einzelnen Balken
        $barHeight = $barGroupHeight / $seriesCount;
        
        // Finde die Nulllinie für die X-Achse
        $zeroX = $this->axes->convertXValueToCoordinate(0, $xAxis, $chartArea);
        
        // Rendere jede Serie
        $seriesIndex = 0;
        foreach ($series as $seriesName => $seriesOptions) {
            // Hole die X-Werte (numerische Werte) für diese Serie
            $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : [];
            
            // Hole die Y-Werte (Kategorien) für diese Serie
            $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
            
            // Für jede Kategorie in der Y-Achse
            for ($categoryIndex = 0; $categoryIndex < $categoryCount; $categoryIndex++) {
                $category = $categories[$categoryIndex];
                
                // Suche den entsprechenden Y-Wert für diese Kategorie
                $idx = array_search($category, $seriesY);
                
                // Wenn die Kategorie nicht gefunden wurde, überspringe sie
                if ($idx === false) {
                    continue;
                }
                
                // Finde den zugehörigen X-Wert (numerisch)
                $value = isset($seriesX[$idx]) ? $seriesX[$idx] : 0;
                
                // Überprüfe, ob der Wert numerisch ist
                if (!is_numeric($value)) {
                    continue;
                }
                
                // Y-Position für den Balken (Mitte der Kategorie + Versatz für die Serie)
                $y = $chartArea['y'] + ($categoryIndex + 0.5) * $categoryHeight - ($barGroupHeight / 2) + $seriesIndex * $barHeight;
                
                // X-Position und Breite für den Balken
                $barWidth = abs($this->axes->convertXValueToCoordinate($value, $xAxis, $chartArea) - $zeroX);
                $barX = $value >= 0 ? $zeroX : $zeroX - $barWidth;
                
                // Mindestbreite für sichtbare Balken
                if ($barWidth < 1) {
                    $barWidth = 1;
                }
                
                // Farbe bestimmen
                $color = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
                
                // Gradient-ID verwenden, falls vorhanden
                $fillColor = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $color;
                
                // Eckenradius anwenden
                $cornerRadius = isset($seriesOptions['bar']) && isset($seriesOptions['bar']['cornerRadius']) ?
                            $seriesOptions['bar']['cornerRadius'] : 0;
                
                // Balken rendern
                $output .= $this->svg->createRect(
                    $barX,
                    $y,
                    $barWidth,
                    $barHeight,
                    [
                        'fill' => $fillColor,
                        'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                        'rx' => $cornerRadius,
                        'ry' => $cornerRadius
                    ]
                );
                
                // Datenwertbeschriftung rendern, falls aktiviert
                if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                    $labelX = $value >= 0 ? $barX + $barWidth + 5 : $barX - 5;
                    $textAnchor = $value >= 0 ? 'start' : 'end';
                    
                    $output .= $this->renderDataLabel(
                        $labelX,
                        $y + $barHeight / 2,
                        $value,
                        $seriesOptions['dataLabels'],
                        $textAnchor
                    );
                }
            }
            
            $seriesIndex++;
        }
        
        return $output;
    }
    
    /**
     * Rendert vertikale gestapelte Balken
     * 
     * @param array $series Array mit Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param int $stackGroupIndex Index der Stapelgruppe
     * @param int $stackGroupCount Anzahl der Stapelgruppen
     * @return string SVG-Elemente der vertikalen gestapelten Balken
     */
    private function renderVerticalStackedBars($series, $xValues, $yValues, $axes, $chartArea, $stackGroupIndex, $stackGroupCount) {
        $output = '';
        
        // Bestimme die zu verwendenden Achsen (erste Serie)
        $firstSeries = reset($series);
        $xAxisId = isset($firstSeries['xAxisId']) ? $firstSeries['xAxisId'] : 0;
        $yAxisId = isset($firstSeries['yAxisId']) ? $firstSeries['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Bestimme die Kategorie-Breite
        $categoryWidth = isset($xAxis['categoryWidth']) ? $xAxis['categoryWidth'] : 0;
        
        // Bestimme die Breite für alle Stapelgruppen zusammen (80% der Kategorie-Breite)
        $allStacksWidth = $categoryWidth * 0.8;
        
        // Berechne die Breite für eine einzelne Stapelgruppe
        $stackGroupWidth = $allStacksWidth / $stackGroupCount;
        
        // Berechne den Abstand zwischen den Stapelgruppen (20% der Stapelgruppenbreite)
        $stackGroupSpacing = $stackGroupWidth * 0.2;
        
        // Berechne die tatsächliche Balkenbreite (80% der Stapelgruppenbreite)
        $barWidth = $stackGroupWidth - $stackGroupSpacing;
        
        // Überprüfe benutzerdefinierte Breite
        if (isset($firstSeries['bar']) && isset($firstSeries['bar']['width']) && $firstSeries['bar']['width'] !== null) {
            $barWidth = min($firstSeries['bar']['width'], $stackGroupWidth * 0.8);
        }
        
        // Berechne den Stapel für jede Kategorie
        $stacks = [];
        foreach ($series as $seriesName => $seriesOptions) {
            $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
            
            foreach ($seriesY as $idx => $value) {
                if (!isset($stacks[$idx])) {
                    $stacks[$idx] = [
                        'positive' => 0,  // Summe positiver Werte
                        'negative' => 0,  // Summe negativer Werte
                        'items' => []
                    ];
                }
                
                // Negative und positive Werte separat stapeln
                if (is_numeric($value)) {
                    if ($value >= 0) {
                        $stackBase = $stacks[$idx]['positive'];
                        $stackEnd = $stackBase + $value;
                        $stacks[$idx]['positive'] = $stackEnd;
                    } else {
                        $stackBase = $stacks[$idx]['negative'];
                        $stackEnd = $stackBase + $value;
                        $stacks[$idx]['negative'] = $stackEnd;
                    }
                    
                    $stacks[$idx]['items'][] = [
                        'seriesName' => $seriesName,
                        'value' => $value,
                        'stackBase' => $stackBase,
                        'stackEnd' => $stackEnd
                    ];
                }
            }
        }
        
        // Finde die Nulllinie für die Y-Achse
        $zeroY = $this->axes->convertYValueToCoordinate(0, $yAxis, $chartArea);
        
        // Rendere jeden Stapel
        foreach ($stacks as $idx => $stack) {
            // Bestimme die X-Position des Balkens, unter Berücksichtigung der Stapelgruppe
            $x = 0;
            
            if ($xAxis['type'] === 'category') {
                // Zentriere die Stapelgruppen innerhalb der Kategorie
                $categoryCenter = $chartArea['x'] + ($idx + 0.5) * $categoryWidth;
                $stacksStartX = $categoryCenter - ($allStacksWidth / 2);
                $x = $stacksStartX + ($stackGroupIndex * $stackGroupWidth) + ($stackGroupSpacing / 2);
            } else {
                // Für andere Achsentypen Kategorie-Index verwenden und Stapelgruppen verteilen
                $categoryCenter = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
                $stacksStartX = $categoryCenter - ($allStacksWidth / 2);
                $x = $stacksStartX + ($stackGroupIndex * $stackGroupWidth) + ($stackGroupSpacing / 2);
            }
            
            // Rendere jeden Balken im Stapel
            foreach ($stack['items'] as $item) {
                $seriesName = $item['seriesName'];
                $seriesOptions = $series[$seriesName];
                $value = $item['value'];
                $stackBase = $item['stackBase'];
                $stackEnd = $item['stackEnd'];
                
                // Konvertiere Werte in Koordinaten
                $y1 = $this->axes->convertYValueToCoordinate($stackBase, $yAxis, $chartArea);
                $y2 = $this->axes->convertYValueToCoordinate($stackEnd, $yAxis, $chartArea);
                
                // Y-Koordinaten tauschen, falls y2 > y1 (negative Werte)
                if ($y2 > $y1) {
                    list($y1, $y2) = [$y2, $y1];
                }
                
                // Balkenoptionen
                $barHeight = abs($y1 - $y2);
                
                // Mindesthöhe für sichtbare Balken
                if ($barHeight < 1) {
                    $barHeight = 1;
                }
                
                // Farbe bestimmen
                $color = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
                
                // Gradient-ID verwenden, falls vorhanden
                $fillColor = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $color;
                
                // Eckenradius nur bei der Oberseite des Balkens anwenden
                $cornerRadius = isset($seriesOptions['bar']) && isset($seriesOptions['bar']['cornerRadius']) ?
                                $seriesOptions['bar']['cornerRadius'] : 0;
                
                // Balken rendern
                $output .= $this->svg->createRect(
                    $x,
                    $y2,
                    $barWidth,
                    $barHeight,
                    [
                        'fill' => $fillColor,
                        'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                        'rx' => $cornerRadius,
                        'ry' => $cornerRadius
                    ]
                );
                
                // Datenwertbeschriftung rendern, falls aktiviert
                if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                    $output .= $this->renderDataLabel(
                        $x + $barWidth / 2,
                        $y2 - 5,
                        $value,
                        $seriesOptions['dataLabels']
                    );
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert vertikale nicht-gestapelte Balken
     * 
     * @param array $series Array mit Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der vertikalen nicht-gestapelten Balken
     */
    private function renderVerticalUnstackedBars($series, $xValues, $yValues, $axes, $chartArea) {
        $output = '';
        
        // Anzahl der Serien
        $seriesCount = count($series);
        
        // Bestimme die zu verwendenden Achsen (erste Serie)
        $firstSeries = reset($series);
        $xAxisId = isset($firstSeries['xAxisId']) ? $firstSeries['xAxisId'] : 0;
        $yAxisId = isset($firstSeries['yAxisId']) ? $firstSeries['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Bestimme die Kategorie-Breite
        $categoryWidth = isset($xAxis['categoryWidth']) ? $xAxis['categoryWidth'] : 0;
        $barGroupWidth = $categoryWidth * 0.8; // 80% der Kategorie-Breite
        $barWidth = $barGroupWidth / $seriesCount;
        
        // Überprüfe benutzerdefinierte Breite
        $maxBarWidth = isset($firstSeries['bar']) && isset($firstSeries['bar']['maxWidth']) ?
                        $firstSeries['bar']['maxWidth'] : 50; // Standardwert, falls nicht angegeben
        if ($barWidth > $maxBarWidth) {
            $barWidth = $maxBarWidth;
            $barGroupWidth = $barWidth * $seriesCount;
        }
        
        // Finde die Nulllinie für die Y-Achse
        $zeroY = $this->axes->convertYValueToCoordinate(0, $yAxis, $chartArea);
        
        // Rendere jede Serie
        $seriesIndex = 0;
        foreach ($series as $seriesName => $seriesOptions) {
            $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
            
            foreach ($seriesY as $idx => $value) {
                // Bestimme die X-Position des Balkens
                $x = 0;
                
                if ($xAxis['type'] === 'category') {
                    // Zentriere die Balkengruppe in der Kategorie
                    $groupStartX = $chartArea['x'] + ($idx + 0.5) * $categoryWidth - $barGroupWidth / 2;
                    $x = $groupStartX + $seriesIndex * $barWidth;
                } else {
                    // Für andere Achsentypen Kategorie-Index verwenden
                    $centerX = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
                    $groupStartX = $centerX - $barGroupWidth / 2;
                    $x = $groupStartX + $seriesIndex * $barWidth;
                }
                
                // Konvertiere Wert in Y-Koordinate
                if (is_numeric($value)) {
                    $y = $this->axes->convertYValueToCoordinate($value, $yAxis, $chartArea);
                    
                    // Balkenoptionen
                    $barHeight = abs($zeroY - $y);
                    $barY = min($zeroY, $y);
                    
                    // Mindesthöhe für sichtbare Balken
                    if ($barHeight < 1) {
                        $barHeight = 1;
                    }
                    
                    // Farbe bestimmen
                    $color = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
                    
                    // Gradient-ID verwenden, falls vorhanden
                    $fillColor = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $color;
                    
                    // Eckenradius anwenden
                    $cornerRadius = isset($seriesOptions['bar']) && isset($seriesOptions['bar']['cornerRadius']) ?
                                    $seriesOptions['bar']['cornerRadius'] : 0;
                    
                    // Balken rendern
                    $output .= $this->svg->createRect(
                        $x,
                        $barY,
                        $barWidth,
                        $barHeight,
                        [
                            'fill' => $fillColor,
                            'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                            'rx' => $cornerRadius,
                            'ry' => $cornerRadius
                        ]
                    );
                    
                    // Datenwertbeschriftung rendern, falls aktiviert
                    if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                        $labelPos = $value >= 0 ? $barY - 5 : $barY + $barHeight + 15;
                        $output .= $this->renderDataLabel(
                            $x + $barWidth / 2,
                            $labelPos,
                            $value,
                            $seriesOptions['dataLabels']
                        );
                    }
                }
            }
            
            $seriesIndex++;
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Datenwertbeschriftung
     * 
     * @param float $x X-Koordinate
     * @param float $y Y-Koordinate
     * @param float $value Wert
     * @param array $labelOptions Optionen für die Beschriftung
     * @param string $anchor Textausrichtung (start, middle, end)
     * @return string SVG-Element der Beschriftung
     */
    private function renderDataLabel($x, $y, $value, $labelOptions, $anchor = 'middle') {
        // Format der Beschriftung (Standard: Wert)
        $format = isset($labelOptions['format']) ? $labelOptions['format'] : '{y}';
        
        // Ersetze Platzhalter im Format
        $label = str_replace('{y}', $this->utils->formatNumber($value), $format);
        
        // Standardwerte für Label-Optionen
        $offsetX = isset($labelOptions['offsetX']) ? $labelOptions['offsetX'] : 0;
        $offsetY = isset($labelOptions['offsetY']) ? $labelOptions['offsetY'] : 0;
        $fontFamily = isset($labelOptions['fontFamily']) ? $labelOptions['fontFamily'] : 'Arial, Helvetica, sans-serif';
        $fontSize = isset($labelOptions['fontSize']) ? $labelOptions['fontSize'] : 12;
        $fontWeight = isset($labelOptions['fontWeight']) ? $labelOptions['fontWeight'] : 'normal';
        $color = isset($labelOptions['color']) ? $labelOptions['color'] : '#333333';
        $rotation = isset($labelOptions['rotation']) ? $labelOptions['rotation'] : 0;
        
        // Beschriftung rendern
        return $this->svg->createText(
            $x + $offsetX,
            $y + $offsetY,
            $label,
            [
                'fontFamily' => $fontFamily,
                'fontSize' => $fontSize,
                'fontWeight' => $fontWeight,
                'fill' => $color,
                'textAnchor' => $anchor,
                'dominantBaseline' => 'middle',
                'rotate' => $rotation
            ]
        );
    }
}
?>