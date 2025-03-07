<?php
/**
 * ChartPieChart - Pie- und Donut-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Pie- und Donut-Diagrammen zuständig.
 * Sie unterstützt auch partielle Kreisdiagramme mit einstellbarem Start- und Endwinkel.
 * Winkellogik: 0° ist Westen, 90° ist Norden, 180° ist Osten, 270° ist Süden.
 * 
 * @version 1.4
 */
class ChartPieChart {
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
     * Rendert ein Pie- oder Donut-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Pie-/Donut-Diagramm-Serien
     * @param array $xValues Array mit X-Werten (Kategorien)
     * @param array $yValues Array mit Y-Werten (Werte)
     * @param array $axes Achsendefinitionen (nicht verwendet für Pie-Charts)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Pie-/Donut-Diagramms
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
        
        // Rendere jede Serie
        foreach ($updatedSeriesGroup as $seriesName => $seriesOptions) {
            $output .= $this->renderPieSeries(
                $seriesName,
                $seriesOptions,
                $xValues,
                $yValues,
                $chartArea
            );
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
     * Rendert eine Pie- oder Donut-Serie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten (Kategorien)
     * @param array $yValues Array mit Y-Werten (Werte)
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Pie-/Donut-Serie
     */
    private function renderPieSeries($seriesName, $seriesOptions, $xValues, $yValues, $chartArea) {
        // Hole die Werte für diese Serie
        $seriesCategories = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesValues = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine Werte vorhanden sind, nichts rendern
        if (empty($seriesValues)) {
            return '';
        }
        
        // Standardfarben für Segmente
        $defaultColors = isset($seriesOptions['colors']) ? $seriesOptions['colors'] : [
            '#5BC9AD', '#DC5244', '#468DF3', '#A0A0A0', '#DDDDDD', 
            '#90E1D2', '#E68C86', '#F8D871', '#7F7F7F', '#333438'
        ];
        
        // Berechne Summe aller Werte für prozentuale Anteile
        $totalValue = array_sum($seriesValues);
        
        // Optimierte Nutzung des verfügbaren Platzes
        $minDimension = min($chartArea['width'], $chartArea['height']);
        $radius = isset($seriesOptions['radius']) ? 
                $seriesOptions['radius'] : 
                $minDimension * 0.45; // 45% der kleineren Dimension

        // Zentriere das Pie/Donut Chart im verfügbaren Raum
        $centerX = isset($seriesOptions['centerX']) ? 
                 $seriesOptions['centerX'] : 
                 $chartArea['x'] + $chartArea['width'] / 2;
        
        $centerY = isset($seriesOptions['centerY']) ? 
                 $seriesOptions['centerY'] : 
                 $chartArea['y'] + $chartArea['height'] / 2;
        
        // Für Donut-Chart: innerer Radius
        $innerRadius = isset($seriesOptions['innerRadius']) ? 
                     $seriesOptions['innerRadius'] : 
                     0; // 0 = Pie-Chart, > 0 = Donut-Chart
        
        // Falls innerRadius als Prozentangabe des Radius definiert ist
        if (is_string($innerRadius) && substr($innerRadius, -1) === '%') {
            $percentage = intval(substr($innerRadius, 0, -1)) / 100;
            $innerRadius = $radius * $percentage;
        }
        
        // Start- und Endwinkel für das Diagramm (in Grad)
        // Westen=0°, Norden=90°, Osten=180°, Süden=270°
        $startAngle = isset($seriesOptions['startAngle']) ? 
                    $seriesOptions['startAngle'] : 
                    0; // Standardmäßig ab Westen (0°)
        
        $endAngle = isset($seriesOptions['endAngle']) ? 
                  $seriesOptions['endAngle'] : 
                  360; // Standardmäßig kompletter Kreis (360°)
        
        // Spezialfall: Voller Kreis (360°)
        $isFullCircle = ($endAngle - $startAngle) >= 360;
        
        // Für SVG-Winkelberechnung: nur bei Teilkreisen die Standardanpassung machen
        if ($isFullCircle) {
            // Bei vollständigem Kreis verwenden wir spezielle Werte
            $startAngle = 0;
            $endAngle = 359.99; // Knapp unter 360° für korrektes SVG-Rendering
        } else {
            // Justierung für SVG-Koordinatensystem (0° = Osten, 90° = Süden)
            // Angepasste Winkelkonvertierung: West=0° entspricht 180° im SVG
            $startAngle = (180 + $startAngle) % 360; // Angepasst für West=0°
            $endAngle = (180 + $endAngle) % 360;     // Angepasst für West=0°
            
            // Sicherstellen, dass der Endwinkel größer als der Startwinkel ist
            if ($endAngle <= $startAngle) {
                $endAngle += 360;
            }
        }
        
        // Abstand zwischen den Segmenten (in Grad)
        $padAngle = isset($seriesOptions['padAngle']) ? 
                  $seriesOptions['padAngle'] : 
                  0;
        
        // Eckenradius für die Segmente
        $cornerRadius = isset($seriesOptions['cornerRadius']) ? 
                      $seriesOptions['cornerRadius'] : 
                      0;
        
        // Individuelle Segment-Definitionen prüfen
        $individualSegments = isset($seriesOptions['segments']) ? $seriesOptions['segments'] : [];
        
        // Output initialisieren
        $output = '';
        
        // Verfügbarer Winkelbereich
        $totalAngle = $endAngle - $startAngle;
        
        // Aktueller Winkel (Startpunkt)
        $currentAngle = $startAngle;
        
        // Für jeden Datenpunkt/Wert ein Segment erstellen
        for ($i = 0; $i < count($seriesValues); $i++) {
            $value = $seriesValues[$i];
            
            // Segmentgröße berechnen (Winkel)
            $angleSize = ($value / $totalValue) * $totalAngle;
            
            // Winkel mit Padding anpassen
            $segmentStartAngle = $currentAngle + $padAngle / 2;
            $segmentEndAngle = $currentAngle + $angleSize - $padAngle / 2;
            
            // Koordinaten für den äußeren Bogen
            $startRadians = deg2rad($segmentStartAngle);
            $endRadians = deg2rad($segmentEndAngle);
            
            $startX = $centerX + $radius * cos($startRadians);
            $startY = $centerY + $radius * sin($startRadians);
            $endX = $centerX + $radius * cos($endRadians);
            $endY = $centerY + $radius * sin($endRadians);
            
            // Koordinaten für den inneren Bogen (bei Donut)
            $innerStartX = $centerX + $innerRadius * cos($startRadians);
            $innerStartY = $centerY + $innerRadius * sin($startRadians);
            $innerEndX = $centerX + $innerRadius * cos($endRadians);
            $innerEndY = $centerY + $innerRadius * sin($endRadians);
            
            // Bestimme die Farbe und andere Attribute für dieses Segment
            $segmentOptions = isset($individualSegments[$i]) ? $individualSegments[$i] : [];
            
            // Bestimme die Farbe für das Segment
            $defaultColor = $defaultColors[$i % count($defaultColors)];
            $color = isset($segmentOptions['color']) ? $segmentOptions['color'] : $defaultColor;
            $fillOpacity = isset($segmentOptions['fillOpacity']) ? $segmentOptions['fillOpacity'] : 1;
            $borderColor = isset($segmentOptions['borderColor']) ? $segmentOptions['borderColor'] : $color;
            $borderWidth = isset($segmentOptions['borderWidth']) ? $segmentOptions['borderWidth'] : 1;
            
            // Bestimme die Füllung (Gradient oder Farbe)
            $fill = isset($segmentOptions['gradientId']) ? $segmentOptions['gradientId'] : $color;
            
            // Segment-Pfad erstellen
            $path = '';
            $largeArcFlag = $angleSize > 180 ? 1 : 0;
            
            // Entscheiden, welche Art von Pfad erstellt werden soll (mit/ohne abgerundete Ecken)
            if ($cornerRadius > 0 && $innerRadius > 0) {
                // Donut-Segment mit abgerundeten Ecken
                $path = $this->createRoundedDonutSegmentPath(
                    $centerX, $centerY,
                    $startX, $startY, $endX, $endY,
                    $innerStartX, $innerStartY, $innerEndX, $innerEndY,
                    $radius, $innerRadius, $cornerRadius, $largeArcFlag
                );
            } else if ($innerRadius > 0) {
                // Donut-Segment ohne abgerundete Ecken
                $path = "M {$startX} {$startY} " .                         // Äußerer Startpunkt
                        "A {$radius} {$radius} 0 {$largeArcFlag} 1 {$endX} {$endY} " . // Äußerer Bogen
                        "L {$innerEndX} {$innerEndY} " .                    // Linie zum inneren Endpunkt
                        "A {$innerRadius} {$innerRadius} 0 {$largeArcFlag} 0 {$innerStartX} {$innerStartY} " . // Innerer Bogen
                        "Z";                                                // Schließen des Pfads
            } else {
                // Pie-Segment
                $path = "M {$centerX} {$centerY} " .                        // Zentrum
                        "L {$startX} {$startY} " .                          // Linie zum äußeren Startpunkt
                        "A {$radius} {$radius} 0 {$largeArcFlag} 1 {$endX} {$endY} " . // Äußerer Bogen
                        "Z";                                                // Schließen des Pfads zum Zentrum
            }
            
            // Segment rendern
            $output .= $this->svg->createPath(
                $path,
                [
                    'fill' => $fill,
                    'fillOpacity' => $fillOpacity,
                    'stroke' => $borderColor,
                    'strokeWidth' => $borderWidth
                ]
            );
            
            // Datenwertbeschriftung rendern, falls aktiviert
            if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                // Position für das Label berechnen (Mitte des Segments)
                $labelAngle = deg2rad($segmentStartAngle + $angleSize / 2);
                $labelRadius = $innerRadius > 0 ? 
                             ($radius + $innerRadius) / 2 : // Bei Donut: Mitte zwischen innerem und äußerem Radius
                             $radius * 0.7; // Bei Pie: 70% des Radius
                
                $labelX = $centerX + $labelRadius * cos($labelAngle);
                $labelY = $centerY + $labelRadius * sin($labelAngle);
                
                // Label-Text formatieren
                $labelText = '';
                $format = isset($seriesOptions['dataLabels']['format']) ? $seriesOptions['dataLabels']['format'] : '{value}';
                
                // Kategorie für dieses Segment
                $category = isset($seriesCategories[$i]) ? $seriesCategories[$i] : '';
                
                // Prozentsatz berechnen
                $percentage = ($value / $totalValue) * 100;
                
                // Platzhalter ersetzen
                $labelText = str_replace('{value}', $this->utils->formatNumber($value), $format);
                $labelText = str_replace('{percentage}', $this->utils->formatNumber($percentage, ['decimals' => 1]) . '%', $labelText);
                $labelText = str_replace('{category}', $category, $labelText);
                
                // Individuelle Label-Optionen pro Segment
                if (isset($segmentOptions['dataLabel'])) {
                    if (isset($segmentOptions['dataLabel']['text'])) {
                        $labelText = $segmentOptions['dataLabel']['text'];
                    }
                }
                
                // Label-Optionen
                $labelOffsetX = isset($seriesOptions['dataLabels']['offsetX']) ? $seriesOptions['dataLabels']['offsetX'] : 0;
                $labelOffsetY = isset($seriesOptions['dataLabels']['offsetY']) ? $seriesOptions['dataLabels']['offsetY'] : 0;
                $labelColor = isset($seriesOptions['dataLabels']['color']) ? $seriesOptions['dataLabels']['color'] : '#333333';
                $labelFontSize = isset($seriesOptions['dataLabels']['fontSize']) ? $seriesOptions['dataLabels']['fontSize'] : 12;
                
                // Individuelle Label-Optionen überschreiben
                if (isset($segmentOptions['dataLabel'])) {
                    if (isset($segmentOptions['dataLabel']['color'])) {
                        $labelColor = $segmentOptions['dataLabel']['color'];
                    }
                    if (isset($segmentOptions['dataLabel']['offsetX'])) {
                        $labelOffsetX = $segmentOptions['dataLabel']['offsetX'];
                    }
                    if (isset($segmentOptions['dataLabel']['offsetY'])) {
                        $labelOffsetY = $segmentOptions['dataLabel']['offsetY'];
                    }
                    if (isset($segmentOptions['dataLabel']['fontSize'])) {
                        $labelFontSize = $segmentOptions['dataLabel']['fontSize'];
                    }
                }
                
                // Label rendern
                $output .= $this->svg->createText(
                    $labelX + $labelOffsetX,
                    $labelY + $labelOffsetY,
                    $labelText,
                    [
                        'fontFamily' => isset($seriesOptions['dataLabels']['fontFamily']) ? $seriesOptions['dataLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => $labelFontSize,
                        'fontWeight' => isset($seriesOptions['dataLabels']['fontWeight']) ? $seriesOptions['dataLabels']['fontWeight'] : 'normal',
                        'fill' => $labelColor,
                        'textAnchor' => 'middle',
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
            
            // Aktuellen Winkel für das nächste Segment aktualisieren
            $currentAngle += $angleSize;
        }
        
        return $output;
    }
    
    /**
     * Erstellt einen SVG-Pfad für ein Donut-Segment mit abgerundeten Ecken
     * 
     * @param float $centerX X-Koordinate des Zentrums
     * @param float $centerY Y-Koordinate des Zentrums
     * @param float $startX X-Koordinate des äußeren Startpunkts
     * @param float $startY Y-Koordinate des äußeren Startpunkts
     * @param float $endX X-Koordinate des äußeren Endpunkts
     * @param float $endY Y-Koordinate des äußeren Endpunkts
     * @param float $innerStartX X-Koordinate des inneren Startpunkts
     * @param float $innerStartY Y-Koordinate des inneren Startpunkts
     * @param float $innerEndX X-Koordinate des inneren Endpunkts
     * @param float $innerEndY Y-Koordinate des inneren Endpunkts
     * @param float $radius Äußerer Radius
     * @param float $innerRadius Innerer Radius
     * @param float $cornerRadius Eckenradius
     * @param int $largeArcFlag Flag für großen Bogen (0 oder 1)
     * @return string SVG-Pfaddaten
     */
    private function createRoundedDonutSegmentPath($centerX, $centerY, $startX, $startY, $endX, $endY, 
                                               $innerStartX, $innerStartY, $innerEndX, $innerEndY,
                                               $radius, $innerRadius, $cornerRadius, $largeArcFlag) {
        // Begrenze den Eckenradius auf einen sinnvollen Wert
        $maxCornerRadius = min($radius - $innerRadius, 10) / 2;
        $cornerRadius = min($cornerRadius, $maxCornerRadius);
        
        // Berechne Hilfspunkte für die Rundungen
        // Für die äußere Kante
        $startAngle = atan2($startY - $centerY, $startX - $centerX);
        $endAngle = atan2($endY - $centerY, $endX - $centerX);
        
        // Berechne Punkte für die äußere Rundung (Start)
        $startOuterX1 = $centerX + $radius * cos($startAngle);
        $startOuterY1 = $centerY + $radius * sin($startAngle);
        $startOuterX2 = $startOuterX1 - $cornerRadius * cos($startAngle);
        $startOuterY2 = $startOuterY1 - $cornerRadius * sin($startAngle);
        
        // Berechne Punkte für die äußere Rundung (Ende)
        $endOuterX1 = $centerX + $radius * cos($endAngle);
        $endOuterY1 = $centerY + $radius * sin($endAngle);
        $endOuterX2 = $endOuterX1 - $cornerRadius * cos($endAngle);
        $endOuterY2 = $endOuterY1 - $cornerRadius * sin($endAngle);
        
        // Berechne Punkte für die innere Rundung (Start)
        $startInnerX1 = $centerX + $innerRadius * cos($startAngle);
        $startInnerY1 = $centerY + $innerRadius * sin($startAngle);
        $startInnerX2 = $startInnerX1 + $cornerRadius * cos($startAngle);
        $startInnerY2 = $startInnerY1 + $cornerRadius * sin($startAngle);
        
        // Berechne Punkte für die innere Rundung (Ende)
        $endInnerX1 = $centerX + $innerRadius * cos($endAngle);
        $endInnerY1 = $centerY + $innerRadius * sin($endAngle);
        $endInnerX2 = $endInnerX1 + $cornerRadius * cos($endAngle);
        $endInnerY2 = $endInnerY1 + $cornerRadius * sin($endAngle);
        
        // Erstelle den SVG-Pfad mit abgerundeten Ecken
        $path = "M {$startOuterX2} {$startOuterY2} " .
                "L {$startOuterX1} {$startOuterY1} " .
                "A {$radius} {$radius} 0 {$largeArcFlag} 1 {$endOuterX1} {$endOuterY1} " .
                "L {$endOuterX2} {$endOuterY2} " .
                "A {$cornerRadius} {$cornerRadius} 0 0 1 {$endInnerX2} {$endInnerY2} " .
                "L {$endInnerX1} {$endInnerY1} " .
                "A {$innerRadius} {$innerRadius} 0 {$largeArcFlag} 0 {$startInnerX1} {$startInnerY1} " .
                "L {$startInnerX2} {$startInnerY2} " .
                "A {$cornerRadius} {$cornerRadius} 0 0 1 {$startOuterX2} {$startOuterY2} " .
                "Z";
        
        return $path;
    }
}
?>