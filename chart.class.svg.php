<?php
/**
 * ChartSVG - SVG-Generator für das PHPChart-System
 * 
 * Diese Klasse stellt Funktionen zum Erstellen und Manipulieren von SVG-Elementen bereit.
 * 
 * @version 1.4
 */
class ChartSVG {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * Konstruktor - Initialisiert die benötigten Objekte
     */
    public function __construct() {
        $this->utils = new ChartUtils();
    }
    
    /**
     * Initialisiert ein SVG-Dokument
     * 
     * @param int $width Breite des SVG
     * @param int $height Höhe des SVG
     * @return string SVG-Header
     */
    public function initSVG($width, $height) {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<svg xmlns="http://www.w3.org/2000/svg" ' .
               'width="' . $width . '" height="' . $height . '" ' .
               'viewBox="0 0 ' . $width . ' ' . $height . '">' . "\n";
    }
    
    /**
     * Schließt ein SVG-Dokument
     * 
     * @return string SVG-Footer
     */
    public function closeSVG() {
        return '</svg>';
    }
    
    /**
     * Erzeugt ein Rechteck
     * 
     * @param float $x X-Koordinate
     * @param float $y Y-Koordinate
     * @param float $width Breite des Rechtecks
     * @param float $height Höhe des Rechtecks
     * @param array $options Optionen für das Rechteck
     * @return string SVG-Rechteck-Element
     */
    public function createRect($x, $y, $width, $height, $options = []) {
        // Standardoptionen
        $defaults = [
            'fill' => '#000000',
            'fillOpacity' => 1,
            'stroke' => 'none',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'strokeDasharray' => '',
            'rx' => 0,
            'ry' => 0,
            'id' => '',
            'class' => '',
            'cursor' => '',
            'transform' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'x="' . $x . '"';
        $attributes[] = 'y="' . $y . '"';
        $attributes[] = 'width="' . $width . '"';
        $attributes[] = 'height="' . $height . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['fill'] != '') {
            // Fill-Attribut unverändert übernehmen (wichtig für Gradienten-URLs)
            $attributes[] = 'fill="' . $options['fill'] . '"';
        }
        
        if ($options['fillOpacity'] != 1) $attributes[] = 'fill-opacity="' . $options['fillOpacity'] . '"';
        if ($options['stroke'] != 'none') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['strokeDasharray'] != '') $attributes[] = 'stroke-dasharray="' . $options['strokeDasharray'] . '"';
        if ($options['rx'] != 0) $attributes[] = 'rx="' . $options['rx'] . '"';
        if ($options['ry'] != 0) $attributes[] = 'ry="' . $options['ry'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<rect ' . implode(' ', $attributes) . ' />' . "\n";
    }
    
    /**
     * Erzeugt einen Kreis
     * 
     * @param float $cx X-Koordinate des Mittelpunkts
     * @param float $cy Y-Koordinate des Mittelpunkts
     * @param float $r Radius des Kreises
     * @param array $options Optionen für den Kreis
     * @return string SVG-Kreis-Element
     */
    public function createCircle($cx, $cy, $r, $options = []) {
        // Standardoptionen
        $defaults = [
            'fill' => '#000000',
            'fillOpacity' => 1,
            'stroke' => 'none',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'strokeDasharray' => '',
            'id' => '',
            'class' => '',
            'cursor' => '',
            'transform' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'cx="' . $cx . '"';
        $attributes[] = 'cy="' . $cy . '"';
        $attributes[] = 'r="' . $r . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['fill'] != '') $attributes[] = 'fill="' . $options['fill'] . '"';
        if ($options['fillOpacity'] != 1) $attributes[] = 'fill-opacity="' . $options['fillOpacity'] . '"';
        if ($options['stroke'] != 'none') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['strokeDasharray'] != '') $attributes[] = 'stroke-dasharray="' . $options['strokeDasharray'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<circle ' . implode(' ', $attributes) . ' />' . "\n";
    }
    
    /**
     * Erzeugt eine Ellipse
     * 
     * @param float $cx X-Koordinate des Mittelpunkts
     * @param float $cy Y-Koordinate des Mittelpunkts
     * @param float $rx X-Radius der Ellipse
     * @param float $ry Y-Radius der Ellipse
     * @param array $options Optionen für die Ellipse
     * @return string SVG-Ellipse-Element
     */
    public function createEllipse($cx, $cy, $rx, $ry, $options = []) {
        // Standardoptionen
        $defaults = [
            'fill' => '#000000',
            'fillOpacity' => 1,
            'stroke' => 'none',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'strokeDasharray' => '',
            'id' => '',
            'class' => '',
            'cursor' => '',
            'transform' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'cx="' . $cx . '"';
        $attributes[] = 'cy="' . $cy . '"';
        $attributes[] = 'rx="' . $rx . '"';
        $attributes[] = 'ry="' . $ry . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['fill'] != '') $attributes[] = 'fill="' . $options['fill'] . '"';
        if ($options['fillOpacity'] != 1) $attributes[] = 'fill-opacity="' . $options['fillOpacity'] . '"';
        if ($options['stroke'] != 'none') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['strokeDasharray'] != '') $attributes[] = 'stroke-dasharray="' . $options['strokeDasharray'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<ellipse ' . implode(' ', $attributes) . ' />' . "\n";
    }
    
    /**
     * Erzeugt eine Linie
     * 
     * @param float $x1 X-Koordinate des Startpunkts
     * @param float $y1 Y-Koordinate des Startpunkts
     * @param float $x2 X-Koordinate des Endpunkts
     * @param float $y2 Y-Koordinate des Endpunkts
     * @param array $options Optionen für die Linie
     * @return string SVG-Linien-Element
     */
    public function createLine($x1, $y1, $x2, $y2, $options = []) {
        // Standardoptionen
        $defaults = [
            'stroke' => '#000000',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'strokeDasharray' => '',
            'strokeLinecap' => 'butt', // butt, round, square
            'id' => '',
            'class' => '',
            'cursor' => '',
            'transform' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'x1="' . $x1 . '"';
        $attributes[] = 'y1="' . $y1 . '"';
        $attributes[] = 'x2="' . $x2 . '"';
        $attributes[] = 'y2="' . $y2 . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['stroke'] != '') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['strokeDasharray'] != '') $attributes[] = 'stroke-dasharray="' . $options['strokeDasharray'] . '"';
        if ($options['strokeLinecap'] != 'butt') $attributes[] = 'stroke-linecap="' . $options['strokeLinecap'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<line ' . implode(' ', $attributes) . ' />' . "\n";
    }
    
    /**
     * Erzeugt einen Pfad
     * 
     * @param string $d Pfaddaten im SVG-Pfad-Format
     * @param array $options Optionen für den Pfad
     * @return string SVG-Pfad-Element
     */
    public function createPath($d, $options = []) {
        // Standardoptionen
        $defaults = [
            'fill' => 'none',
            'fillOpacity' => 1,
            'fillRule' => 'nonzero', // nonzero, evenodd
            'stroke' => '#000000',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'strokeDasharray' => '',
            'strokeLinecap' => 'butt', // butt, round, square
            'strokeLinejoin' => 'miter', // miter, round, bevel
            'id' => '',
            'class' => '',
            'cursor' => '',
            'transform' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'd="' . $d . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['fill'] != '') $attributes[] = 'fill="' . $options['fill'] . '"';
        if ($options['fillOpacity'] != 1) $attributes[] = 'fill-opacity="' . $options['fillOpacity'] . '"';
        if ($options['fillRule'] != 'nonzero') $attributes[] = 'fill-rule="' . $options['fillRule'] . '"';
        if ($options['stroke'] != '') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['strokeDasharray'] != '') $attributes[] = 'stroke-dasharray="' . $options['strokeDasharray'] . '"';
        if ($options['strokeLinecap'] != 'butt') $attributes[] = 'stroke-linecap="' . $options['strokeLinecap'] . '"';
        if ($options['strokeLinejoin'] != 'miter') $attributes[] = 'stroke-linejoin="' . $options['strokeLinejoin'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<path ' . implode(' ', $attributes) . ' />' . "\n";
    }
    
    /**
     * Erzeugt einen Polygonzug
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $options Optionen für den Polygonzug
     * @return string SVG-Polyline-Element
     */
    public function createPolyline($points, $options = []) {
        // Standardoptionen
        $defaults = [
            'fill' => 'none',
            'fillOpacity' => 1,
            'stroke' => '#000000',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'strokeDasharray' => '',
            'strokeLinecap' => 'butt', // butt, round, square
            'strokeLinejoin' => 'miter', // miter, round, bevel
            'id' => '',
            'class' => '',
            'cursor' => '',
            'transform' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Punkte in das richtige Format konvertieren
        $pointsString = '';
        foreach ($points as $point) {
            if (isset($point[0]) && isset($point[1])) {
                $pointsString .= $point[0] . ',' . $point[1] . ' ';
            }
        }
        $pointsString = trim($pointsString);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'points="' . $pointsString . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['fill'] != '') $attributes[] = 'fill="' . $options['fill'] . '"';
        if ($options['fillOpacity'] != 1) $attributes[] = 'fill-opacity="' . $options['fillOpacity'] . '"';
        if ($options['stroke'] != '') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['strokeDasharray'] != '') $attributes[] = 'stroke-dasharray="' . $options['strokeDasharray'] . '"';
        if ($options['strokeLinecap'] != 'butt') $attributes[] = 'stroke-linecap="' . $options['strokeLinecap'] . '"';
        if ($options['strokeLinejoin'] != 'miter') $attributes[] = 'stroke-linejoin="' . $options['strokeLinejoin'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<polyline ' . implode(' ', $attributes) . ' />' . "\n";
    }
    
    /**
     * Erzeugt ein Polygon
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $options Optionen für das Polygon
     * @return string SVG-Polygon-Element
     */
    public function createPolygon($points, $options = []) {
        // Standardoptionen
        $defaults = [
            'fill' => '#000000',
            'fillOpacity' => 1,
            'fillRule' => 'nonzero', // nonzero, evenodd
            'stroke' => 'none',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'strokeDasharray' => '',
            'strokeLinejoin' => 'miter', // miter, round, bevel
            'id' => '',
            'class' => '',
            'cursor' => '',
            'transform' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Punkte in das richtige Format konvertieren
        $pointsString = '';
        foreach ($points as $point) {
            if (isset($point[0]) && isset($point[1])) {
                $pointsString .= $point[0] . ',' . $point[1] . ' ';
            }
        }
        $pointsString = trim($pointsString);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'points="' . $pointsString . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['fill'] != '') $attributes[] = 'fill="' . $options['fill'] . '"';
        if ($options['fillOpacity'] != 1) $attributes[] = 'fill-opacity="' . $options['fillOpacity'] . '"';
        if ($options['fillRule'] != 'nonzero') $attributes[] = 'fill-rule="' . $options['fillRule'] . '"';
        if ($options['stroke'] != 'none') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['strokeDasharray'] != '') $attributes[] = 'stroke-dasharray="' . $options['strokeDasharray'] . '"';
        if ($options['strokeLinejoin'] != 'miter') $attributes[] = 'stroke-linejoin="' . $options['strokeLinejoin'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<polygon ' . implode(' ', $attributes) . ' />' . "\n";
    }
    
    /**
     * Erzeugt ein Textelement
     * 
     * @param float $x X-Koordinate
     * @param float $y Y-Koordinate
     * @param string $text Text
     * @param array $options Optionen für das Textelement
     * @return string SVG-Text-Element
     */
    public function createText($x, $y, $text, $options = []) {
        // Standardoptionen
        $defaults = [
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => 12,
            'fontWeight' => 'normal',
            'fontStyle' => 'normal',
            'textAnchor' => 'start', // start, middle, end
            'dominantBaseline' => 'auto', // auto, middle, hanging
            'fill' => '#000000',
            'fillOpacity' => 1,
            'stroke' => 'none',
            'strokeWidth' => 1,
            'strokeOpacity' => 1,
            'rotate' => 0,
            'id' => '',
            'class' => '',
            'cursor' => '',
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'x="' . $x . '"';
        $attributes[] = 'y="' . $y . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['fontFamily'] != '') $attributes[] = 'font-family="' . $options['fontFamily'] . '"';
        if ($options['fontSize'] != '') $attributes[] = 'font-size="' . $options['fontSize'] . '"';
        if ($options['fontWeight'] != 'normal') $attributes[] = 'font-weight="' . $options['fontWeight'] . '"';
        if ($options['fontStyle'] != 'normal') $attributes[] = 'font-style="' . $options['fontStyle'] . '"';
        if ($options['textAnchor'] != 'start') $attributes[] = 'text-anchor="' . $options['textAnchor'] . '"';
        if ($options['dominantBaseline'] != 'auto') $attributes[] = 'dominant-baseline="' . $options['dominantBaseline'] . '"';
        if ($options['fill'] != '') $attributes[] = 'fill="' . $options['fill'] . '"';
        if ($options['fillOpacity'] != 1) $attributes[] = 'fill-opacity="' . $options['fillOpacity'] . '"';
        if ($options['stroke'] != 'none') $attributes[] = 'stroke="' . $options['stroke'] . '"';
        if ($options['strokeWidth'] != 1) $attributes[] = 'stroke-width="' . $options['strokeWidth'] . '"';
        if ($options['strokeOpacity'] != 1) $attributes[] = 'stroke-opacity="' . $options['strokeOpacity'] . '"';
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['cursor'] != '') $attributes[] = 'cursor="' . $options['cursor'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        // Rotation anwenden, falls angegeben
        if ($options['rotate'] !== 0) {
            $attributes[] = 'transform="rotate(' . $options['rotate'] . ' ' . $x . ' ' . $y . ')"';
        }
        
        return '<text ' . implode(' ', $attributes) . '>' . htmlspecialchars($text) . '</text>' . "\n";
    }
    
    /**
     * Erzeugt einen linearen Gradienten
     * 
     * @param string $id ID des Gradienten
     * @param array $stops Array mit Gradient-Stops (Position und Farbe)
     * @param array $options Optionen für den Gradienten
     * @return string SVG-Gradient-Definition
     */
    public function createLinearGradient($id, $stops, $options = []) {
        // Standardoptionen
        $defaults = [
            'x1' => '0%',
            'y1' => '0%',
            'x2' => '0%',
            'y2' => '100%',
            'gradientUnits' => 'objectBoundingBox', // objectBoundingBox, userSpaceOnUse
            'spreadMethod' => 'pad' // pad, reflect, repeat
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'id="' . $id . '"';
        $attributes[] = 'x1="' . $options['x1'] . '"';
        $attributes[] = 'y1="' . $options['y1'] . '"';
        $attributes[] = 'x2="' . $options['x2'] . '"';
        $attributes[] = 'y2="' . $options['y2'] . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['gradientUnits'] != 'objectBoundingBox') {
            $attributes[] = 'gradientUnits="' . $options['gradientUnits'] . '"';
        }
        if ($options['spreadMethod'] != 'pad') {
            $attributes[] = 'spreadMethod="' . $options['spreadMethod'] . '"';
        }
        
        // Gradient-Stops erstellen
        $stopElements = '';
        foreach ($stops as $stop) {
            $offset = isset($stop['offset']) ? $stop['offset'] : '0%';
            $color = isset($stop['color']) ? $stop['color'] : '#000000';
            $opacity = isset($stop['opacity']) ? $stop['opacity'] : 1;
            
            $stopAttributes = [];
            $stopAttributes[] = 'offset="' . $offset . '"';
            $stopAttributes[] = 'stop-color="' . $color . '"';
            
            if ($opacity != 1) {
                $stopAttributes[] = 'stop-opacity="' . $opacity . '"';
            }
            
            $stopElements .= '<stop ' . implode(' ', $stopAttributes) . ' />' . "\n";
        }
        
        // Gradient zusammensetzen
        return '<linearGradient ' . implode(' ', $attributes) . '>' . "\n" .
               $stopElements .
               '</linearGradient>' . "\n";
    }
    
    /**
     * Erzeugt einen radialen Gradienten
     * 
     * @param string $id ID des Gradienten
     * @param array $stops Array mit Gradient-Stops (Position und Farbe)
     * @param array $options Optionen für den Gradienten
     * @return string SVG-Gradient-Definition
     */
    public function createRadialGradient($id, $stops, $options = []) {
        // Standardoptionen
        $defaults = [
            'cx' => '50%',
            'cy' => '50%',
            'r' => '50%',
            'fx' => null, // Falls null, wird cx verwendet
            'fy' => null, // Falls null, wird cy verwendet
            'gradientUnits' => 'objectBoundingBox', // objectBoundingBox, userSpaceOnUse
            'spreadMethod' => 'pad' // pad, reflect, repeat
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Fallback für fx und fy
        if ($options['fx'] === null) $options['fx'] = $options['cx'];
        if ($options['fy'] === null) $options['fy'] = $options['cy'];
        
        // Attribute zusammenstellen
        $attributes = [];
        $attributes[] = 'id="' . $id . '"';
        $attributes[] = 'cx="' . $options['cx'] . '"';
        $attributes[] = 'cy="' . $options['cy'] . '"';
        $attributes[] = 'r="' . $options['r'] . '"';
        $attributes[] = 'fx="' . $options['fx'] . '"';
        $attributes[] = 'fy="' . $options['fy'] . '"';
        
        // Optionale Attribute hinzufügen
        if ($options['gradientUnits'] != 'objectBoundingBox') {
            $attributes[] = 'gradientUnits="' . $options['gradientUnits'] . '"';
        }
        if ($options['spreadMethod'] != 'pad') {
            $attributes[] = 'spreadMethod="' . $options['spreadMethod'] . '"';
        }
        
        // Gradient-Stops erstellen
        $stopElements = '';
        foreach ($stops as $stop) {
            $offset = isset($stop['offset']) ? $stop['offset'] : '0%';
            $color = isset($stop['color']) ? $stop['color'] : '#000000';
            $opacity = isset($stop['opacity']) ? $stop['opacity'] : 1;
            
            $stopAttributes = [];
            $stopAttributes[] = 'offset="' . $offset . '"';
            $stopAttributes[] = 'stop-color="' . $color . '"';
            
            if ($opacity != 1) {
                $stopAttributes[] = 'stop-opacity="' . $opacity . '"';
            }
            
            $stopElements .= '<stop ' . implode(' ', $stopAttributes) . ' />' . "\n";
        }
        
        // Gradient zusammensetzen
        return '<radialGradient ' . implode(' ', $attributes) . '>' . "\n" .
               $stopElements .
               '</radialGradient>' . "\n";
    }
    
    /**
     * Erzeugt eine Gruppe von SVG-Elementen
     * 
     * @param string $content Inhalt der Gruppe
     * @param array $options Optionen für die Gruppe
     * @return string SVG-Gruppen-Element
     */
    public function createGroup($content, $options = []) {
        // Standardoptionen
        $defaults = [
            'id' => '',
            'class' => '',
            'transform' => '',
            'opacity' => 1,
            'filter' => '',
            'mask' => '',
            'clipPath' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        
        // Optionale Attribute hinzufügen
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        if ($options['transform'] != '') $attributes[] = 'transform="' . $options['transform'] . '"';
        if ($options['opacity'] != 1) $attributes[] = 'opacity="' . $options['opacity'] . '"';
        if ($options['filter'] != '') $attributes[] = 'filter="' . $options['filter'] . '"';
        if ($options['mask'] != '') $attributes[] = 'mask="' . $options['mask'] . '"';
        if ($options['clipPath'] != '') $attributes[] = 'clip-path="' . $options['clipPath'] . '"';
        
        return '<g ' . implode(' ', $attributes) . '>' . "\n" .
               $content .
               '</g>' . "\n";
    }
    
    /**
     * Rendert den Hintergrund des Diagramms
     * 
     * @param array $chartArea Daten zum Zeichenbereich
     * @param string $color Hintergrundfarbe
     * @param float $borderRadius Eckenradius
     * @return string SVG-Hintergrund-Element
     */
    public function renderBackground($chartArea, $color, $borderRadius) {
        return $this->createRect(
            $chartArea['x'],
            $chartArea['y'],
            $chartArea['width'],
            $chartArea['height'],
            [
                'fill' => $color,
                'rx' => $borderRadius,
                'ry' => $borderRadius
            ]
        );
    }
    
    /**
     * Rendert das Gitter des Diagramms
     * 
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $axes Achsendefinitionen
     * @param array $gridOptions Gitteroptionen
     * @return string SVG-Gitter-Elemente
     */
    public function renderGrid($chartArea, $axes, $gridOptions) {
        $grid = '';
        
        // Für jede X-Achse
        foreach ($axes['x'] as $xAxisId => $xAxis) {
            if (!isset($xAxis['grid']) || !isset($xAxis['grid']['enabled']) || !$xAxis['grid']['enabled']) continue;
            
            // Für jeden Tick der X-Achse
            foreach ($xAxis['ticks'] as $tick) {
                $x = $tick['position'];
                
                // Vertikale Gitternetzlinie
                $grid .= $this->createLine(
                    $x,
                    $chartArea['y'],
                    $x,
                    $chartArea['y'] + $chartArea['height'],
                    [
                        'stroke' => isset($xAxis['grid']['color']) ? $xAxis['grid']['color'] : '#e0e0e0',
                        'strokeWidth' => isset($xAxis['grid']['width']) ? $xAxis['grid']['width'] : 1,
                        'strokeDasharray' => isset($xAxis['grid']['dashArray']) ? $xAxis['grid']['dashArray'] : ''
                    ]
                );
            }
        }
        
        // Für jede Y-Achse
        foreach ($axes['y'] as $yAxisId => $yAxis) {
            if (!isset($yAxis['grid']) || !isset($yAxis['grid']['enabled']) || !$yAxis['grid']['enabled']) continue;
            
            // Für jeden Tick der Y-Achse
            foreach ($yAxis['ticks'] as $tick) {
                $y = $tick['position'];
                
                // Horizontale Gitternetzlinie
                $grid .= $this->createLine(
                    $chartArea['x'],
                    $y,
                    $chartArea['x'] + $chartArea['width'],
                    $y,
                    [
                        'stroke' => isset($yAxis['grid']['color']) ? $yAxis['grid']['color'] : '#e0e0e0',
                        'strokeWidth' => isset($yAxis['grid']['width']) ? $yAxis['grid']['width'] : 1,
                        'strokeDasharray' => isset($yAxis['grid']['dashArray']) ? $yAxis['grid']['dashArray'] : ''
                    ]
                );
            }
        }
        
        return $grid;
    }
    
    /**
     * Rendert einen Tooltip
     * 
     * @param float $x X-Koordinate
     * @param float $y Y-Koordinate
     * @param string $content Inhalt des Tooltips
     * @param array $options Optionen für den Tooltip
     * @return string SVG-Tooltip-Element (title)
     */
    public function createTooltip($x, $y, $content, $options = []) {
        // Standardoptionen
        $defaults = [
            'id' => '',
            'class' => ''
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Attribute zusammenstellen
        $attributes = [];
        
        // Optionale Attribute hinzufügen
        if ($options['id'] != '') $attributes[] = 'id="' . $options['id'] . '"';
        if ($options['class'] != '') $attributes[] = 'class="' . $options['class'] . '"';
        
        return '<title ' . implode(' ', $attributes) . '>' . htmlspecialchars($content) . '</title>' . "\n";
    }
    
    /**
     * Erzeugt ein Clipping-Pfad-Element
     * 
     * @param string $id ID des Clipping-Pfads
     * @param string $content Inhalt des Clipping-Pfads (SVG-Formen)
     * @return string SVG-Clipping-Pfad-Element
     */
    public function createClipPath($id, $content) {
        return '<clipPath id="' . $id . '">' . "\n" .
               $content .
               '</clipPath>' . "\n";
    }
    
    /**
     * Erzeugt eine Definitionssektion für Gradienten, Clippath usw.
     * 
     * @param string $content Inhalt der Definitionssektion
     * @return string SVG-Definitions-Element
     */
    public function createDefs($content) {
        return '<defs>' . "\n" .
               $content .
               '</defs>' . "\n";
    }
}
?>