<?php
/**
 * ChartLegend - Legendenverwaltung für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Diagrammlegenden zuständig.
 * 
 * @version 1.1
 */
class ChartLegend {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
    /**
     * Konstruktor - Initialisiert die benötigten Objekte
     */
    public function __construct() {
        $this->utils = new ChartUtils();
        $this->svg = new ChartSVG();
    }
    
    /**
     * Rendert die Legende des Diagramms
     * 
     * @param array $series Array mit Serien-Definitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $legendOptions Optionen für die Legende
     * @param array $axes Achsendefinitionen (für bessere Positionierung)
     * @return string SVG-Elemente der Legende
     */
    public function render($series, $chartArea, $legendOptions, $axes = null) {
        if (!isset($legendOptions['enabled']) || !$legendOptions['enabled'] || empty($series)) {
            return '';
        }
        
        // Sammle Legendeneinträge
        $items = [];
        foreach ($series as $name => $options) {
            if (!isset($options['showInLegend']) || $options['showInLegend']) {
                $items[] = [
                    'name' => $name,
                    'text' => !empty($options['legendText']) ? $options['legendText'] : $name,
                    'color' => !empty($options['color']) ? $options['color'] : '#000000',
                    'type' => $options['type'],
                    'options' => $options
                ];
            }
        }
        
        if (empty($items)) {
            return '';
        }
        
        // Berechne die Größe der Legende
        $legendMetrics = $this->calculateLegendMetrics($items, $legendOptions);
        
        // Bestimme die Position der Legende
        $legendPosition = $this->calculateLegendPosition($legendMetrics, $chartArea, $legendOptions, $axes);
        
        // Rendere die Legende
        return $this->renderLegend($items, $legendPosition, $legendMetrics, $legendOptions);
    }
    
    /**
     * Berechnet die Größe der Legende
     * 
     * @param array $items Array mit Legendeneinträgen
     * @param array $legendOptions Optionen für die Legende
     * @return array Metriken der Legende (width, height, etc.)
     */
    private function calculateLegendMetrics($items, $legendOptions) {
        $itemHeight = isset($legendOptions['fontSize']) ? $legendOptions['fontSize'] * 1.2 : 14.4;
        $symbolSize = isset($legendOptions['symbolSize']) ? $legendOptions['symbolSize'] : 10;
        $symbolSpacing = isset($legendOptions['symbolSpacing']) ? $legendOptions['symbolSpacing'] : 5;
        $itemSpacing = isset($legendOptions['itemSpacing']) ? $legendOptions['itemSpacing'] : 20;
        $padding = isset($legendOptions['padding']) ? $legendOptions['padding'] : 10;
        
        // Bestimme die Größe jedes Eintrags
        $itemMetrics = [];
        $maxItemWidth = 0;
        
        foreach ($items as $item) {
            // Geschätzte Breite des Textes (grobe Annäherung)
            $textWidth = strlen($item['text']) * (isset($legendOptions['fontSize']) ? $legendOptions['fontSize'] * 0.6 : 7.2);
            
            // Breite des Eintrags: Symbol + Abstand + Text
            $itemWidth = $symbolSize + $symbolSpacing + $textWidth;
            $maxItemWidth = max($maxItemWidth, $itemWidth);
            
            $itemMetrics[] = [
                'width' => $itemWidth,
                'height' => $itemHeight
            ];
        }
        
        // Je nach Layout die Gesamtgröße berechnen
        $totalWidth = 0;
        $totalHeight = 0;
        
        if (!isset($legendOptions['layout']) || $legendOptions['layout'] === 'horizontal') {
            // Horizontales Layout: Summe der Breiten + Abstände
            $totalWidth = array_sum(array_column($itemMetrics, 'width')) + ($itemSpacing * (count($items) - 1));
            $totalHeight = $itemHeight;
        } else { // 'vertical'
            // Vertikales Layout: Maximale Breite, Summe der Höhen + Abstände
            $totalWidth = $maxItemWidth;
            $totalHeight = array_sum(array_column($itemMetrics, 'height')) + ($itemSpacing * (count($items) - 1));
        }
        
        // Padding hinzufügen
        $totalWidth += 2 * $padding;
        $totalHeight += 2 * $padding;
        
        return [
            'items' => $itemMetrics,
            'maxItemWidth' => $maxItemWidth,
            'totalWidth' => $totalWidth,
            'totalHeight' => $totalHeight,
            'padding' => $padding,
            'itemSpacing' => $itemSpacing
        ];
    }
    
    /**
     * Berechnet die Position der Legende
     * 
     * @param array $legendMetrics Metriken der Legende
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $legendOptions Optionen für die Legende
     * @param array $axes Achsendefinitionen (für bessere Positionierung)
     * @return array Position der Legende (x, y)
     */
    private function calculateLegendPosition($legendMetrics, $chartArea, $legendOptions, $axes = null) {
        $position = isset($legendOptions['position']) ? $legendOptions['position'] : 'bottom';
        $align = isset($legendOptions['align']) ? $legendOptions['align'] : 'center';
        
        $x = 0;
        $y = 0;
        
        // Zusätzlicher Abstand für Achsenbeschriftungen
        $axisLabelBuffer = 40; // Standardwert
        
        // Berechne besseren Abstand, wenn Achseninformationen verfügbar sind
        if ($axes && isset($axes['x']) && !empty($axes['x'])) {
            $xAxis = reset($axes['x']);
            if (isset($xAxis['labels']) && isset($xAxis['labels']['fontSize'])) {
                $fontSize = $xAxis['labels']['fontSize'];
                $rotation = isset($xAxis['labels']['rotation']) ? $xAxis['labels']['rotation'] : 0;
                
                // Bei rotierten Labels mehr Platz lassen
                if ($rotation > 0) {
                    // Berechne Platz basierend auf Rotation und Länge der längsten Beschriftung
                    // max(Länge) * sin(Rotation) * FontSize als grobe Schätzung
                    $maxLabelLength = 0;
                    if (isset($xAxis['ticks'])) {
                        foreach ($xAxis['ticks'] as $tick) {
                            $maxLabelLength = max($maxLabelLength, strlen($tick['label']));
                        }
                    } else {
                        $maxLabelLength = 10; // Standardannahme
                    }
                    
                    $rotationRad = deg2rad($rotation);
                    $axisLabelBuffer = $maxLabelLength * sin($rotationRad) * $fontSize + 20;
                } else {
                    $axisLabelBuffer = $fontSize * 2 + 10; // Normaler Abstand für nicht-rotierte Labels
                }
            }
        }
        
        if ($position === 'custom') {
            // Benutzerdefinierte Position
            $x = isset($legendOptions['x']) ? $legendOptions['x'] : 0;
            $y = isset($legendOptions['y']) ? $legendOptions['y'] : 0;
        } else {
            switch ($position) {
                case 'top':
                    $y = $chartArea['y'] - $legendMetrics['totalHeight'] - 10; // Kleiner Abstand
                    
                    // Horizontale Ausrichtung
                    if ($align === 'left') {
                        $x = $chartArea['x'];
                    } else if ($align === 'right') {
                        $x = $chartArea['x'] + $chartArea['width'] - $legendMetrics['totalWidth'];
                    } else { // 'center'
                        $x = $chartArea['x'] + ($chartArea['width'] - $legendMetrics['totalWidth']) / 2;
                    }
                    break;
                    
                case 'bottom':
                    // Erhöhter Abstand, um Achsenbeschriftungen nicht zu überlappen
                    $y = $chartArea['y'] + $chartArea['height'] + $axisLabelBuffer;
                    
                    // Horizontale Ausrichtung
                    if ($align === 'left') {
                        $x = $chartArea['x'];
                    } else if ($align === 'right') {
                        $x = $chartArea['x'] + $chartArea['width'] - $legendMetrics['totalWidth'];
                    } else { // 'center'
                        $x = $chartArea['x'] + ($chartArea['width'] - $legendMetrics['totalWidth']) / 2;
                    }
                    break;
                    
                case 'left':
                    $x = $chartArea['x'] - $legendMetrics['totalWidth'] - 10; // Kleiner Abstand
                    
                    // Vertikale Ausrichtung
                    if ($align === 'top') {
                        $y = $chartArea['y'];
                    } else if ($align === 'bottom') {
                        $y = $chartArea['y'] + $chartArea['height'] - $legendMetrics['totalHeight'];
                    } else { // 'center'
                        $y = $chartArea['y'] + ($chartArea['height'] - $legendMetrics['totalHeight']) / 2;
                    }
                    break;
                    
                case 'right':
                    $x = $chartArea['x'] + $chartArea['width'] + 10; // Kleiner Abstand
                    
                    // Vertikale Ausrichtung
                    if ($align === 'top') {
                        $y = $chartArea['y'];
                    } else if ($align === 'bottom') {
                        $y = $chartArea['y'] + $chartArea['height'] - $legendMetrics['totalHeight'];
                    } else { // 'center'
                        $y = $chartArea['y'] + ($chartArea['height'] - $legendMetrics['totalHeight']) / 2;
                    }
                    break;
            }
        }
        
        return [
            'x' => $x,
            'y' => $y
        ];
    }
    
    /**
     * Rendert die Legende
     * 
     * @param array $items Array mit Legendeneinträgen
     * @param array $position Position der Legende
     * @param array $metrics Metriken der Legende
     * @param array $options Optionen für die Legende
     * @return string SVG-Elemente der Legende
     */
    private function renderLegend($items, $position, $metrics, $options) {
        $output = '';
        
        // Hintergrund der Legende
        if (isset($options['background']) && $options['background'] !== '') {
            $output .= $this->svg->createRect(
                $position['x'],
                $position['y'],
                $metrics['totalWidth'],
                $metrics['totalHeight'],
                [
                    'fill' => $options['background'],
                    'rx' => isset($options['borderRadius']) ? $options['borderRadius'] : 0,
                    'ry' => isset($options['borderRadius']) ? $options['borderRadius'] : 0
                ]
            );
            
            // Rahmen hinzufügen, falls aktiviert
            if (isset($options['border']) && isset($options['border']['enabled']) && $options['border']['enabled']) {
                $output .= $this->svg->createRect(
                    $position['x'],
                    $position['y'],
                    $metrics['totalWidth'],
                    $metrics['totalHeight'],
                    [
                        'fill' => 'none',
                        'stroke' => isset($options['border']['color']) ? $options['border']['color'] : '#cccccc',
                        'strokeWidth' => isset($options['border']['width']) ? $options['border']['width'] : 1,
                        'rx' => isset($options['borderRadius']) ? $options['borderRadius'] : 0,
                        'ry' => isset($options['borderRadius']) ? $options['borderRadius'] : 0
                    ]
                );
            }
        }
        
        // Legendeneinträge rendern
        $x = $position['x'] + $metrics['padding'];
        $y = $position['y'] + $metrics['padding'];
        
        foreach ($items as $index => $item) {
            $itemWidth = $metrics['items'][$index]['width'];
            $itemHeight = $metrics['items'][$index]['height'];
            
            // Symbol rendern
            $symbolX = $x;
            $symbolY = $y + $itemHeight / 2;
            $output .= $this->renderLegendSymbol(
                $item['type'],
                $symbolX,
                $symbolY,
                isset($options['symbolSize']) ? $options['symbolSize'] : 10,
                $item['color'],
                $item['options']
            );
            
            // Text rendern
            $textX = $x + (isset($options['symbolSize']) ? $options['symbolSize'] : 10) + 
                     (isset($options['symbolSpacing']) ? $options['symbolSpacing'] : 5);
            $textY = $y + $itemHeight / 2;
            $output .= $this->svg->createText(
                $textX,
                $textY,
                $item['text'],
                [
                    'fontFamily' => isset($options['fontFamily']) ? $options['fontFamily'] : 'Arial, Helvetica, sans-serif',
                    'fontSize' => isset($options['fontSize']) ? $options['fontSize'] : 12,
                    'fontWeight' => isset($options['fontWeight']) ? $options['fontWeight'] : 'normal',
                    'fill' => isset($options['color']) ? $options['color'] : '#333333',
                    'textAnchor' => 'start',
                    'dominantBaseline' => 'middle'
                ]
            );
            
            // Position für den nächsten Eintrag aktualisieren
            if (!isset($options['layout']) || $options['layout'] === 'horizontal') {
                $x += $itemWidth + $metrics['itemSpacing'];
            } else { // 'vertical'
                $y += $itemHeight + $metrics['itemSpacing'];
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert ein Symbol für die Legende
     * 
     * @param string $type Typ der Serie
     * @param float $x X-Koordinate
     * @param float $y Y-Koordinate
     * @param float $size Größe des Symbols
     * @param string $color Farbe des Symbols
     * @param array $options Optionen der Serie
     * @return string SVG-Element des Symbols
     */
    private function renderLegendSymbol($type, $x, $y, $size, $color, $options) {
        switch ($type) {
            case 'bar':
                // Rechteck für Bar Chart
                return $this->svg->createRect(
                    $x,
                    $y - $size / 2,
                    $size,
                    $size,
                    [
                        'fill' => $color,
                        'fillOpacity' => isset($options['fillOpacity']) ? $options['fillOpacity'] : 0.8,
                        'rx' => isset($options['bar']) && isset($options['bar']['cornerRadius']) ? $options['bar']['cornerRadius'] : 0,
                        'ry' => isset($options['bar']) && isset($options['bar']['cornerRadius']) ? $options['bar']['cornerRadius'] : 0
                    ]
                );
                
            case 'line':
            case 'spline':
                // Linie mit optionalem Punkt
                $output = $this->svg->createLine(
                    $x,
                    $y,
                    $x + $size,
                    $y,
                    [
                        'stroke' => $color,
                        'strokeWidth' => isset($options['line']) && isset($options['line']['width']) ? $options['line']['width'] : 2,
                        'strokeDasharray' => isset($options['line']) && isset($options['line']['dashArray']) ? $options['line']['dashArray'] : ''
                    ]
                );
                
                // Punkt hinzufügen, falls aktiviert
                if (isset($options['point']) && isset($options['point']['enabled']) && $options['point']['enabled']) {
                    $output .= $this->renderPointSymbol(
                        $x + $size / 2,
                        $y,
                        isset($options['point']['size']) ? $options['point']['size'] : 5,
                        isset($options['point']['shape']) ? $options['point']['shape'] : 'circle',
                        isset($options['point']['color']) && $options['point']['color'] ? $options['point']['color'] : $color,
                        isset($options['point']['borderColor']) ? $options['point']['borderColor'] : '',
                        isset($options['point']['borderWidth']) ? $options['point']['borderWidth'] : 1
                    );
                }
                
                return $output;
                
            case 'area':
                // Fläche als Rechteck
                return $this->svg->createRect(
                    $x,
                    $y - $size / 2,
                    $size,
                    $size,
                    [
                        'fill' => $color,
                        'fillOpacity' => isset($options['area']) && isset($options['area']['fillOpacity']) ? $options['area']['fillOpacity'] : 0.4,
                        'stroke' => $color,
                        'strokeWidth' => isset($options['area']) && isset($options['area']['strokeWidth']) ? $options['area']['strokeWidth'] : 2
                    ]
                );
                
            case 'pie':
            case 'donut':
                // Kreis für Pie/Donut Chart
                return $this->svg->createCircle(
                    $x + $size / 2,
                    $y,
                    $size / 2,
                    [
                        'fill' => $color,
                        'fillOpacity' => isset($options['fillOpacity']) ? $options['fillOpacity'] : 0.8
                    ]
                );
                
            case 'scatter':
                // Punkt für Scatter Chart
                return $this->renderPointSymbol(
                    $x + $size / 2,
                    $y,
                    isset($options['point']['size']) ? $options['point']['size'] : 5,
                    isset($options['point']['shape']) ? $options['point']['shape'] : 'circle',
                    isset($options['point']['color']) ? $options['point']['color'] : $color,
                    isset($options['point']['borderColor']) ? $options['point']['borderColor'] : '',
                    isset($options['point']['borderWidth']) ? $options['point']['borderWidth'] : 1
                );
                
            case 'bubble':
                // Kreis für Bubble Chart
                return $this->svg->createCircle(
                    $x + $size / 2,
                    $y,
                    $size / 2,
                    [
                        'fill' => $color,
                        'fillOpacity' => isset($options['fillOpacity']) ? $options['fillOpacity'] : 0.8,
                        'stroke' => isset($options['point']['borderColor']) ? $options['point']['borderColor'] : '',
                        'strokeWidth' => isset($options['point']['borderWidth']) ? $options['point']['borderWidth'] : 1
                    ]
                );
                
            case 'radar':
            case 'polar':
                // Dreieck für Radar/Polar Chart
                $points = [
                    [$x + $size / 2, $y - $size / 2],
                    [$x, $y + $size / 2],
                    [$x + $size, $y + $size / 2]
                ];
                
                return $this->svg->createPolygon(
                    $points,
                    [
                        'fill' => $color,
                        'fillOpacity' => isset($options['fillOpacity']) ? $options['fillOpacity'] : 0.8
                    ]
                );
                
            default:
                // Standard: Quadrat
                return $this->svg->createRect(
                    $x,
                    $y - $size / 2,
                    $size,
                    $size,
                    [
                        'fill' => $color,
                        'fillOpacity' => isset($options['fillOpacity']) ? $options['fillOpacity'] : 0.8
                    ]
                );
        }
    }
    
    /**
     * Rendert ein Punktsymbol für die Legende
     * 
     * @param float $x X-Koordinate
     * @param float $y Y-Koordinate
     * @param float $size Größe des Punkts
     * @param string $shape Form des Punkts
     * @param string $color Farbe des Punkts
     * @param string $borderColor Rahmenfarbe des Punkts
     * @param float $borderWidth Rahmenbreite des Punkts
     * @return string SVG-Element des Punkts
     */
    private function renderPointSymbol($x, $y, $size, $shape, $color, $borderColor, $borderWidth) {
        $halfSize = $size / 2;
        
        switch ($shape) {
            case 'circle':
                return $this->svg->createCircle(
                    $x,
                    $y,
                    $halfSize,
                    [
                        'fill' => $color,
                        'stroke' => $borderColor,
                        'strokeWidth' => $borderWidth
                    ]
                );
                
            case 'square':
                return $this->svg->createRect(
                    $x - $halfSize,
                    $y - $halfSize,
                    $size,
                    $size,
                    [
                        'fill' => $color,
                        'stroke' => $borderColor,
                        'strokeWidth' => $borderWidth
                    ]
                );
                
            case 'triangle':
                $points = [
                    [$x, $y - $halfSize],
                    [$x - $halfSize, $y + $halfSize],
                    [$x + $halfSize, $y + $halfSize]
                ];
                
                return $this->svg->createPolygon(
                    $points,
                    [
                        'fill' => $color,
                        'stroke' => $borderColor,
                        'strokeWidth' => $borderWidth
                    ]
                );
                
            case 'diamond':
                $points = [
                    [$x, $y - $halfSize],
                    [$x + $halfSize, $y],
                    [$x, $y + $halfSize],
                    [$x - $halfSize, $y]
                ];
                
                return $this->svg->createPolygon(
                    $points,
                    [
                        'fill' => $color,
                        'stroke' => $borderColor,
                        'strokeWidth' => $borderWidth
                    ]
                );
                
            default:
                // Standardmäßig Kreis
                return $this->svg->createCircle(
                    $x,
                    $y,
                    $halfSize,
                    [
                        'fill' => $color,
                        'stroke' => $borderColor,
                        'strokeWidth' => $borderWidth
                    ]
                );
        }
    }
}
?>