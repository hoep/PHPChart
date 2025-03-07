<?php
/**
 * ChartAxes - Achsenverwaltung für das PHPChart-System
 * 
 * Diese Klasse ist für die Berechnung und Darstellung von Achsen im Diagramm zuständig.
 * Sie unterstützt verschiedene Achsentypen und Skalierungen.
 * 
 * @version 2.3
 */
class ChartAxes {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
    /**
     * @var bool Flag für horizontale Balkendiagramme
     */
    private $horizontalBars = false;
    
    /**
     * Konstruktor - Initialisiert die benötigten Objekte
     */
    public function __construct() {
        $this->utils = new ChartUtils();
        $this->svg = new ChartSVG();
    }
    
    /**
     * Setzt das Flag für horizontale Balkendiagramme
     * 
     * @param bool $value Wert für das horizontale Balken-Flag
     */
    public function setHorizontalBars($value) {
        $this->horizontalBars = $value;
    }
    
    /**
     * Bereitet eine X-Achse für die Darstellung vor
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param int $id ID der Achse
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    public function prepareXAxis(&$xAxis, $id, $xValues, $yValues, $chartArea) {
        // Bei horizontalen Balken wird die X-Achse anders behandelt
        if ($this->horizontalBars) {
            // X-Achse als horizontale Achse mit Werten vorbereiten
            $this->calculateHorizontalXAxisPosition($xAxis, $id, $chartArea);
            // Initialisiere ticks vor dem Aufruf
            $xAxis['ticks'] = [];
            $xAxis['type'] = 'numeric'; // Für horizontale Balken ist die X-Achse numerisch
            $this->calculateNumericXAxisTicks($xAxis, $xValues, $chartArea, $xAxis['ticks']);
        } else {
            // Normale vertikale Behandlung
            $this->calculateXAxisPosition($xAxis, $id, $chartArea);
            $this->calculateXAxisTicks($xAxis, $xValues, $chartArea);
        }
    }
    
    /**
     * Bereitet eine Y-Achse für die Darstellung vor
     * 
     * @param array &$yAxis Referenz auf die Y-Achsendefinition
     * @param int $id ID der Achse
     * @param array $yValues Array mit Y-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    public function prepareYAxis(&$yAxis, $id, $yValues, $chartArea) {
        // Bei horizontalen Balken wird die Y-Achse anders behandelt
        if ($this->horizontalBars) {
            // Y-Achse als vertikale Achse mit Kategorien vorbereiten
            $this->calculateVerticalYAxisPosition($yAxis, $id, $chartArea);
            // Initialisiere ticks vor dem Aufruf
            $yAxis['ticks'] = [];
            $yAxis['type'] = 'category'; // Für horizontale Balken ist die Y-Achse kategorisch
            $this->calculateCategoryYAxisTicks($yAxis, $yValues, $chartArea, $yAxis['ticks']);
        } else {
            // Normale horizontale Behandlung
            $this->calculateYAxisPosition($yAxis, $id, $chartArea);
            $this->calculateYAxisTicks($yAxis, $yValues, $chartArea);
        }
    }
    
    /**
     * Berechnet die Position einer horizontalen X-Achse (für horizontale Balken)
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param int $id ID der Achse
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    private function calculateHorizontalXAxisPosition(&$xAxis, $id, $chartArea) {
        $position = isset($xAxis['position']) ? $xAxis['position'] : 'bottom';
        $yPosition = 0;
        
        if ($position === 'bottom') {
            // Unten
            $yPosition = $chartArea['y'] + $chartArea['height'];
            if ($id > 0) {
                $yPosition += 40 * $id;
            }
        } else {
            // Oben
            $yPosition = $chartArea['y'];
            if ($id > 0) {
                $yPosition -= 40 * $id;
            }
        }
        
        // Benutzerdefinierte Offsets einbeziehen, wenn vorhanden
        $offsetX = isset($xAxis['offsetX']) ? $xAxis['offsetX'] : 0;
        $offsetY = isset($xAxis['offsetY']) ? $xAxis['offsetY'] : 0;
        
        // X-Achse als horizontale Linie
        $xAxis['axisPosition'] = [
            'x1' => $chartArea['x'] + $offsetX,
            'y1' => $yPosition + $offsetY,
            'x2' => $chartArea['x'] + $chartArea['width'] + $offsetX,
            'y2' => $yPosition + $offsetY
        ];
    }
    
    /**
     * Berechnet die Position einer vertikalen Y-Achse (für horizontale Balken)
     * 
     * @param array &$yAxis Referenz auf die Y-Achsendefinition
     * @param int $id ID der Achse
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    private function calculateVerticalYAxisPosition(&$yAxis, $id, $chartArea) {
        $position = isset($yAxis['position']) ? $yAxis['position'] : 'left';
        $xPosition = 0;
        
        if ($position === 'left') {
            // Links
            $xPosition = $chartArea['x'];
            if ($id > 0) {
                $xPosition -= 40 * $id;
            }
        } else {
            // Rechts
            $xPosition = $chartArea['x'] + $chartArea['width'];
            if ($id > 0) {
                $xPosition += 40 * $id;
            }
        }
        
        // Benutzerdefinierte Offsets einbeziehen, wenn vorhanden
        $offsetX = isset($yAxis['offsetX']) ? $yAxis['offsetX'] : 0;
        $offsetY = isset($yAxis['offsetY']) ? $yAxis['offsetY'] : 0;
        
        // Y-Achse als vertikale Linie
        $yAxis['axisPosition'] = [
            'x1' => $xPosition + $offsetX,
            'y1' => $chartArea['y'] + $offsetY,
            'x2' => $xPosition + $offsetX,
            'y2' => $chartArea['y'] + $chartArea['height'] + $offsetY
        ];
    }
    
    /**
     * Berechnet die Position einer X-Achse
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param int $id ID der Achse
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    private function calculateXAxisPosition(&$xAxis, $id, $chartArea) {
        // X-Achsenposition basierend auf Position (top oder bottom) und ID
        $position = isset($xAxis['position']) ? $xAxis['position'] : 'bottom';
        $yPosition = 0;
        
        if ($position === 'bottom') {
            // Erste Achse am unteren Rand, weitere nach unten
            $yPosition = $chartArea['y'] + $chartArea['height'];
            
            // Wenn es nicht die erste Achse ist, verschiebe sie nach unten
            if ($id > 0) {
                $yPosition += 40 * $id; // 40 Pixel Abstand pro Achse
            }
        } else { // position === 'top'
            // Erste Achse am oberen Rand, weitere nach oben
            $yPosition = $chartArea['y'];
            
            // Wenn es nicht die erste Achse ist, verschiebe sie nach oben
            if ($id > 0) {
                $yPosition -= 40 * $id; // 40 Pixel Abstand pro Achse
            }
        }
        
        // Benutzerdefinierte Offsets einbeziehen, wenn vorhanden
        $offsetX = isset($xAxis['offsetX']) ? $xAxis['offsetX'] : 0;
        $offsetY = isset($xAxis['offsetY']) ? $xAxis['offsetY'] : 0;
        
        // Achsenposition speichern
        $xAxis['axisPosition'] = [
            'x1' => $chartArea['x'] + $offsetX,
            'y1' => $yPosition + $offsetY,
            'x2' => $chartArea['x'] + $chartArea['width'] + $offsetX,
            'y2' => $yPosition + $offsetY
        ];
    }
    
    /**
     * Berechnet die Position einer Y-Achse
     * 
     * @param array &$yAxis Referenz auf die Y-Achsendefinition
     * @param int $id ID der Achse
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    private function calculateYAxisPosition(&$yAxis, $id, $chartArea) {
        // Y-Achsenposition basierend auf Position (left oder right) und ID
        $position = isset($yAxis['position']) ? $yAxis['position'] : 'left';
        $xPosition = 0;
        
        if ($position === 'left') {
            // Erste Achse am linken Rand, weitere nach links
            $xPosition = $chartArea['x'];
            
            // Wenn es nicht die erste Achse ist, verschiebe sie nach links
            if ($id > 0) {
                $xPosition -= 40 * $id; // 40 Pixel Abstand pro Achse
            }
        } else { // position === 'right'
            // Erste Achse am rechten Rand, weitere nach rechts
            $xPosition = $chartArea['x'] + $chartArea['width'];
            
            // Wenn es nicht die erste Achse ist, verschiebe sie nach rechts
            if ($id > 0) {
                $xPosition += 40 * $id; // 40 Pixel Abstand pro Achse
            }
        }
        
        // Benutzerdefinierte Offsets einbeziehen, wenn vorhanden
        $offsetX = isset($yAxis['offsetX']) ? $yAxis['offsetX'] : 0;
        $offsetY = isset($yAxis['offsetY']) ? $yAxis['offsetY'] : 0;
        
        // Achsenposition speichern
        $yAxis['axisPosition'] = [
            'x1' => $xPosition + $offsetX,
            'y1' => $chartArea['y'] + $offsetY,
            'x2' => $xPosition + $offsetX,
            'y2' => $chartArea['y'] + $chartArea['height'] + $offsetY
        ];
    }
    
    /**
     * Berechnet die Tick-Positionen für eine X-Achse
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param array $xValues Array mit X-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    private function calculateXAxisTicks(&$xAxis, $xValues, $chartArea) {
        $xAxisType = isset($xAxis['type']) ? $xAxis['type'] : 'category';
        $ticks = [];
        
        switch ($xAxisType) {
            case 'category':
                // Kategorieachse (Strings oder Zahlen als Kategorien)
                $this->calculateCategoryXAxisTicks($xAxis, $xValues, $chartArea, $ticks);
                break;
                
            case 'numeric':
                // Numerische Achse
                $this->calculateNumericXAxisTicks($xAxis, $xValues, $chartArea, $ticks);
                break;
                
            case 'time':
                // Zeitachse
                $this->calculateTimeXAxisTicks($xAxis, $xValues, $chartArea, $ticks);
                break;
                
            case 'log':
                // Logarithmische Achse
                $this->calculateLogXAxisTicks($xAxis, $xValues, $chartArea, $ticks);
                break;
                
            case 'string':
                // Stringachse (wie Kategorieachse, aber Beschriftungen genau an X-Position)
                $this->calculateStringXAxisTicks($xAxis, $xValues, $chartArea, $ticks);
                break;
        }
        
        // Ticks in der Achsendefinition speichern
        $xAxis['ticks'] = $ticks;
    }
    
    /**
     * Berechnet die Tick-Positionen für eine kategoriebezogene X-Achse
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param array $xValues Array mit X-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateCategoryXAxisTicks(&$xAxis, $xValues, $chartArea, &$ticks) {
        // Wenn keine X-Werte vorhanden sind oder keine Kategorie als default angegeben ist, verwende Zähler
        $categories = [];
        
        if (isset($xValues['default']) && !empty($xValues['default'])) {
            $categories = $xValues['default'];
        } elseif (isset($xAxis['categories']) && !empty($xAxis['categories'])) {
            $categories = $xAxis['categories'];
        } else {
            // Wenn keine Kategorien angegeben sind, erstelle eine Standardliste
            $maxCount = 0;
            foreach ($xValues as $seriesX) {
                $maxCount = max($maxCount, count($seriesX));
            }
            
            // Erstelle Standardkategorien
            for ($i = 0; $i < $maxCount; $i++) {
                $categories[] = (string)($i + 1);
            }
        }
        
        // Verhindere Division durch Null, wenn keine Kategorien vorhanden sind
        $categoryCount = count($categories);
        if ($categoryCount === 0) {
            // Standardwerte setzen, um Fehler zu vermeiden
            $xAxis['categoryWidth'] = 0;
            $xAxis['categoriesCount'] = 0;
            return;
        }
        
        // Berechne den verfügbaren Platz und die Kategorie-Breite
        $availableWidth = $chartArea['width'];
        $categoryWidth = $availableWidth / $categoryCount;
        
        // Berechne Tick-Positionen
        for ($i = 0; $i < $categoryCount; $i++) {
            $label = $categories[$i];
            $x = $chartArea['x'] + ($i + 0.5) * $categoryWidth; // Mitte der Kategorie
            
            // Berücksichtige benutzerdefinierte Offsets, wenn vorhanden
            $offsetX = isset($xAxis['offsetX']) ? $xAxis['offsetX'] : 0;
            
            $ticks[] = [
                'value' => $i,
                'label' => $label,
                'position' => $x + $offsetX
            ];
        }
        
        // Speichere berechnete Kategorie-Breite für spätere Verwendung
        $xAxis['categoryWidth'] = $categoryWidth;
        $xAxis['categoriesCount'] = $categoryCount;
    }
    
    /**
     * Berechnet die Tick-Positionen für eine kategoriebezogene Y-Achse
     * 
     * @param array &$yAxis Referenz auf die Y-Achsendefinition
     * @param array $yValues Array mit Y-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateCategoryYAxisTicks(&$yAxis, $yValues, $chartArea, &$ticks) {
        // Wenn keine Kategorie als default angegeben ist, verwende Zähler
        $categories = [];
        
        if (isset($yAxis['categories']) && !empty($yAxis['categories'])) {
            $categories = $yAxis['categories'];
        } else {
            // Wenn keine Kategorien definiert sind, versuche sie aus den Daten zu extrahieren
            if (isset($yValues['default']) && !empty($yValues['default'])) {
                $categories = $yValues['default'];
            } else {
                // Wenn keine direkten Kategorien gefunden wurden, sammle eindeutige Kategorien aus den Y-Werten
                $allCategories = [];
                foreach ($yValues as $seriesName => $seriesY) {
                    foreach ($seriesY as $category) {
                        if (!in_array($category, $allCategories)) {
                            $allCategories[] = $category;
                        }
                    }
                }
                
                if (!empty($allCategories)) {
                    $categories = $allCategories;
                } else {
                    // Fallback: Bestimme die Anzahl der Kategorien aus den Daten
                    $maxCount = 0;
                    foreach ($yValues as $seriesY) {
                        $maxCount = max($maxCount, count($seriesY));
                    }
                    
                    // Erstelle Standardkategorien
                    for ($i = 0; $i < $maxCount; $i++) {
                        $categories[] = (string)($i + 1);
                    }
                }
            }
        }
        
        // Verhindere Division durch Null, wenn keine Kategorien vorhanden sind
        $categoryCount = count($categories);
        if ($categoryCount === 0) {
            // Standardwerte setzen, um Fehler zu vermeiden
            $yAxis['categoryHeight'] = 0;
            $yAxis['categoriesCount'] = 0;
            return;
        }
        
        // Berechne den verfügbaren Platz und die Kategorie-Höhe
        $availableHeight = $chartArea['height'];
        $categoryHeight = $availableHeight / $categoryCount;
        
        // Berücksichtige benutzerdefinierte Offsets, wenn vorhanden
        $offsetY = isset($yAxis['offsetY']) ? $yAxis['offsetY'] : 0;
        
        // Berechne Tick-Positionen (genau in der Mitte jeder Kategorie)
        for ($i = 0; $i < $categoryCount; $i++) {
            $label = $categories[$i];
            
            // Berechnung der exakten Mitte der Kategorie
            $y = $chartArea['y'] + ($i + 0.5) * $categoryHeight;
            
            $ticks[] = [
                'value' => $i,
                'label' => $label,
                'position' => $y + $offsetY
            ];
        }
        
        // Speichere berechnete Kategorie-Höhe für spätere Verwendung
        $yAxis['categoryHeight'] = $categoryHeight;
        $yAxis['categoriesCount'] = $categoryCount;
        $yAxis['categories'] = $categories;
    }
    
    /**
     * Berechnet die Tick-Positionen für eine numerische X-Achse
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param array $xValues Array mit X-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateNumericXAxisTicks(&$xAxis, $xValues, $chartArea, &$ticks) {
        // Finde Min- und Max-Werte
        $min = isset($xAxis['min']) ? $xAxis['min'] : null;
        $max = isset($xAxis['max']) ? $xAxis['max'] : null;
        
        // Wenn Min oder Max nicht gesetzt sind, berechne sie aus den Daten
        if ($min === null || $max === null) {
            $allValues = [];
            
            // Sammle alle X-Werte
            foreach ($xValues as $seriesName => $series) {
                if (is_array($series)) {
                    foreach ($series as $value) {
                        if (is_numeric($value)) {
                            $allValues[] = $value;
                        }
                    }
                }
            }
            
            // Bei horizontalen Balken brauchen wir auch Werte für die Länge der Balken
            if ($this->horizontalBars) {
                // Stelle sicher, dass 0 im Bereich enthalten ist für Balkendiagramme
                $allValues[] = 0;
            }
            
            // Prüfe, ob Werte vorhanden sind
            if (empty($allValues)) {
                // Standardwerte setzen, wenn keine Daten vorhanden sind
                $min = $min === null ? 0 : $min;
                $max = $max === null ? 100 : $max;
            } else {
                // Finde Min und Max
                if ($min === null) {
                    $min = $this->utils->findMin([$allValues]);
                    // Runde den Minimalwert ab
                    $min = floor($min);
                }
                
                if ($max === null) {
                    $max = $this->utils->findMax([$allValues]);
                    // Runde den Maximalwert auf und füge etwas Platz hinzu (10%)
                    $max = ceil($max * 1.1);
                }
            }
        }
        
        // Berechne "schöne" Skala-Grenzen und Ticks
        $tickAmount = isset($xAxis['tickAmount']) ? $xAxis['tickAmount'] : 5;
        $scale = $this->utils->calculateNiceScale($min, $max, $tickAmount);
        
        // Aktualisiere Min und Max mit den berechneten Werten
        $min = $scale['min'];
        $max = $scale['max'];
        $tickInterval = $scale['tickInterval'];
        
        // Verhindere Division durch Null, wenn der Bereich zu klein ist
        if ($max <= $min) {
            $max = $min + 1;
        }
        
        // Berechne die Skalierung für die X-Achse
        $scaleX = $chartArea['width'] / ($max - $min);
        
        // Berücksichtige benutzerdefinierte Offsets, wenn vorhanden
        $offsetX = isset($xAxis['offsetX']) ? $xAxis['offsetX'] : 0;
        
        // Berechne Tick-Positionen
        for ($value = $min; $value <= $max; $value += $tickInterval) {
            $x = $chartArea['x'] + ($value - $min) * $scaleX;
            
            // Formatierungsoptionen für die Beschriftungen
            $labelOptions = [
                'decimals' => isset($xAxis['labels']) && isset($xAxis['labels']['decimals']) ? $xAxis['labels']['decimals'] : null,
                'prefix' => isset($xAxis['labels']) && isset($xAxis['labels']['prefix']) ? $xAxis['labels']['prefix'] : '',
                'suffix' => isset($xAxis['labels']) && isset($xAxis['labels']['suffix']) ? $xAxis['labels']['suffix'] : ''
            ];
            
            $ticks[] = [
                'value' => $value,
                'label' => $this->utils->formatNumber($value, $labelOptions),
                'position' => $x + $offsetX
            ];
        }
        
        // Speichere berechnete Min/Max/Scale-Werte für spätere Verwendung
        $xAxis['computedMin'] = $min;
        $xAxis['computedMax'] = $max;
        $xAxis['computedScale'] = $scaleX;
    }
    
    /**
     * Berechnet die Tick-Positionen für eine Zeit-X-Achse
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param array $xValues Array mit X-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateTimeXAxisTicks(&$xAxis, $xValues, $chartArea, &$ticks) {
        // Implementation wie vorher...
    }
    
    /**
     * Berechnet die Tick-Positionen für eine logarithmische X-Achse
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param array $xValues Array mit X-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateLogXAxisTicks(&$xAxis, $xValues, $chartArea, &$ticks) {
        // Implementation wie vorher...
    }
    
    /**
     * Berechnet die Tick-Positionen für eine String-X-Achse
     * 
     * @param array &$xAxis Referenz auf die X-Achsendefinition
     * @param array $xValues Array mit X-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateStringXAxisTicks(&$xAxis, $xValues, $chartArea, &$ticks) {
        // Implementation wie vorher...
    }
    
    /**
     * Berechnet die Tick-Positionen für eine Y-Achse
     * 
     * @param array &$yAxis Referenz auf die Y-Achsendefinition
     * @param array $yValues Array mit Y-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @return void
     */
    private function calculateYAxisTicks(&$yAxis, $yValues, $chartArea) {
        $yAxisType = isset($yAxis['type']) ? $yAxis['type'] : 'numeric';
        $ticks = [];
        
        switch ($yAxisType) {
            case 'category':
                // Kategorie-Achse (wichtig für horizontale Balken)
                $this->calculateCategoryYAxisTicks($yAxis, $yValues, $chartArea, $ticks);
                break;
                
            case 'numeric':
                // Numerische Achse
                $this->calculateNumericYAxisTicks($yAxis, $yValues, $chartArea, $ticks);
                break;
                
            case 'log':
                // Logarithmische Achse
                $this->calculateLogYAxisTicks($yAxis, $yValues, $chartArea, $ticks);
                break;
        }
        
        // Ticks in der Achsendefinition speichern
        $yAxis['ticks'] = $ticks;
    }
    
    /**
     * Berechnet die Tick-Positionen für eine numerische Y-Achse
     * 
     * @param array &$yAxis Referenz auf die Y-Achsendefinition
     * @param array $yValues Array mit Y-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateNumericYAxisTicks(&$yAxis, $yValues, $chartArea, &$ticks) {
        // Finde Min- und Max-Werte
        $min = isset($yAxis['min']) ? $yAxis['min'] : null;
        $max = isset($yAxis['max']) ? $yAxis['max'] : null;
        
        // Wenn Min oder Max nicht gesetzt sind, berechne sie aus den Daten
        if ($min === null || $max === null) {
            $allValues = [];
            
            // Sammle alle Y-Werte
            foreach ($yValues as $series) {
                if (is_array($series)) {
                    foreach ($series as $value) {
                        if (is_numeric($value)) {
                            $allValues[] = $value;
                        }
                    }
                }
            }
            
            // Stelle sicher, dass 0 im Bereich enthalten ist für Balkendiagramme
            $allValues[] = 0;
            
            // Prüfe, ob Werte vorhanden sind
            if (empty($allValues)) {
                // Standardwerte setzen, wenn keine Daten vorhanden sind
                $min = $min === null ? 0 : $min;
                $max = $max === null ? 10 : $max;
            } else {
                // Finde Min und Max
                if ($min === null) {
                    $min = $this->utils->findMin([$allValues]);
                    // Runde den Minimalwert ab (für "schönere" Skalen)
                    $min = floor($min);
                }
                
                if ($max === null) {
                    $max = $this->utils->findMax([$allValues]);
                    // Runde den Maximalwert auf (für "schönere" Skalen) und füge etwas Platz hinzu (10%)
                    $max = ceil($max * 1.1);
                }
            }
        }
        
        // Berechne "schöne" Skala-Grenzen und Ticks
        $tickAmount = isset($yAxis['tickAmount']) ? $yAxis['tickAmount'] : 5;
        $scale = $this->utils->calculateNiceScale($min, $max, $tickAmount, true);
        
        // Aktualisiere Min und Max mit den berechneten Werten
        $min = $scale['min'];
        $max = $scale['max'];
        $tickInterval = $scale['tickInterval'];
        
        // Verhindere Division durch Null, wenn der Bereich zu klein ist
        if ($max <= $min) {
            $max = $min + 1;
        }
        
        // Berechne die Skalierung für die Y-Achse
        $scaleY = $chartArea['height'] / ($max - $min);
        
        // Berücksichtige benutzerdefinierte Offsets, wenn vorhanden
        $offsetY = isset($yAxis['offsetY']) ? $yAxis['offsetY'] : 0;
        
        // Formatierungsoptionen für die Beschriftungen
        $labelOptions = [
            'decimals' => isset($yAxis['labels']) && isset($yAxis['labels']['decimals']) ? $yAxis['labels']['decimals'] : null,
            'prefix' => isset($yAxis['labels']) && isset($yAxis['labels']['prefix']) ? $yAxis['labels']['prefix'] : '',
            'suffix' => isset($yAxis['labels']) && isset($yAxis['labels']['suffix']) ? $yAxis['labels']['suffix'] : ''
        ];
        
        // Berechne Tick-Positionen
        for ($value = $min; $value <= $max; $value += $tickInterval) {
            // Y-Position ist invertiert, da SVG-Koordinaten von oben nach unten gehen
            $y = $chartArea['y'] + $chartArea['height'] - ($value - $min) * $scaleY;
            
            $ticks[] = [
                'value' => $value,
                'label' => $this->utils->formatNumber($value, $labelOptions),
                'position' => $y + $offsetY
            ];
        }
        
        // Speichere berechnete Min/Max/Scale-Werte für spätere Verwendung
        $yAxis['computedMin'] = $min;
        $yAxis['computedMax'] = $max;
        $yAxis['computedScale'] = $scaleY;
    }
    
    /**
     * Berechnet die Tick-Positionen für eine logarithmische Y-Achse
     * 
     * @param array &$yAxis Referenz auf die Y-Achsendefinition
     * @param array $yValues Array mit Y-Werten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array &$ticks Referenz auf das Ticks-Array
     * @return void
     */
    private function calculateLogYAxisTicks(&$yAxis, $yValues, $chartArea, &$ticks) {
        // Implementation wie vorher...
    }
    
    /**
     * Rendert eine X-Achse
     * 
     * @param array $xAxis X-Achsendefinition
     * @param int $id ID der Achse
     * @return string SVG-Elemente der X-Achse
     */
    public function renderXAxis($xAxis, $id) {
        if (!isset($xAxis['enabled']) || !$xAxis['enabled']) {
            return '';
        }
        
        $output = '';
        
        // Achsenlinie rendern
        if (isset($xAxis['line']) && isset($xAxis['line']['enabled']) && $xAxis['line']['enabled']) {
            $output .= $this->svg->createLine(
                $xAxis['axisPosition']['x1'],
                $xAxis['axisPosition']['y1'],
                $xAxis['axisPosition']['x2'],
                $xAxis['axisPosition']['y2'],
                [
                    'stroke' => isset($xAxis['line']['color']) ? $xAxis['line']['color'] : '#999999',
                    'strokeWidth' => isset($xAxis['line']['width']) ? $xAxis['line']['width'] : 1,
                    'strokeDasharray' => isset($xAxis['line']['dashArray']) ? $xAxis['line']['dashArray'] : ''
                ]
            );
        }
        
        // Ticks und Beschriftungen rendern
        if (isset($xAxis['ticks'])) {
            foreach ($xAxis['ticks'] as $tick) {
                // Tick-Linie rendern
                if (isset($xAxis['ticks']['enabled']) || !isset($xAxis['ticks']['enabled'])) {
                    $tickSize = isset($xAxis['ticks']['size']) ? $xAxis['ticks']['size'] : 6;
                    $tickY1 = $xAxis['axisPosition']['y1'];
                    $tickY2 = isset($xAxis['position']) && $xAxis['position'] === 'bottom' ? 
                              $tickY1 + $tickSize : 
                              $tickY1 - $tickSize;
                    
                    $output .= $this->svg->createLine(
                        $tick['position'],
                        $tickY1,
                        $tick['position'],
                        $tickY2,
                        [
                            'stroke' => isset($xAxis['ticks']['color']) ? $xAxis['ticks']['color'] : '#999999',
                            'strokeWidth' => isset($xAxis['ticks']['width']) ? $xAxis['ticks']['width'] : 1
                        ]
                    );
                }
                
                // Beschriftung rendern
                if (isset($xAxis['labels']) && (!isset($xAxis['labels']['enabled']) || $xAxis['labels']['enabled'])) {
                    $tickSize = isset($xAxis['ticks']['size']) ? $xAxis['ticks']['size'] : 6;
                    $fontSize = isset($xAxis['labels']['fontSize']) ? $xAxis['labels']['fontSize'] : 12;
                    
                    $labelY = isset($xAxis['position']) && $xAxis['position'] === 'bottom' ? 
                              $xAxis['axisPosition']['y1'] + $tickSize + $fontSize : 
                              $xAxis['axisPosition']['y1'] - $tickSize - 5;
                    
                    // Label-Offset für X-Achsen-Beschriftungen
                    $labelOffsetX = isset($xAxis['labels']['offsetX']) ? $xAxis['labels']['offsetX'] : 0;
                    $labelOffsetY = isset($xAxis['labels']['offsetY']) ? $xAxis['labels']['offsetY'] : 0;
                    
                    $output .= $this->svg->createText(
                        $tick['position'] + $labelOffsetX,
                        $labelY + $labelOffsetY,
                        $tick['label'],
                        [
                            'fontFamily' => isset($xAxis['labels']['fontFamily']) ? $xAxis['labels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                            'fontSize' => $fontSize,
                            'fontWeight' => isset($xAxis['labels']['fontWeight']) ? $xAxis['labels']['fontWeight'] : 'normal',
                            'fill' => isset($xAxis['labels']['color']) ? $xAxis['labels']['color'] : '#333333',
                            'textAnchor' => isset($xAxis['labels']['align']) ? $xAxis['labels']['align'] : 'middle',
                            'rotate' => isset($xAxis['labels']['rotation']) ? $xAxis['labels']['rotation'] : 0
                        ]
                    );
                }
            }
        }
        
        // Achsentitel rendern
        if (isset($xAxis['title']) && isset($xAxis['title']['enabled']) && $xAxis['title']['enabled'] === true && 
            isset($xAxis['title']['text']) && !empty($xAxis['title']['text'])) {
            
            $titleX = $xAxis['axisPosition']['x1'] + ($xAxis['axisPosition']['x2'] - $xAxis['axisPosition']['x1']) / 2;
            
            $tickSize = isset($xAxis['ticks']['size']) ? $xAxis['ticks']['size'] : 6;
            $fontSize = isset($xAxis['labels']['fontSize']) ? $xAxis['labels']['fontSize'] : 12;
            
            // Näher an die Achse rücken - 2.5x Schriftgröße als Standard
            $baseOffset = 2.5 * $fontSize;
            
            // Benutzerdefinierte Verschiebung verwenden, falls angegeben
            $offsetY = isset($xAxis['title']['offsetY']) ? $xAxis['title']['offsetY'] : $baseOffset;
            
            // Einfachere Berechnung der Y-Position
            $titleY = $xAxis['axisPosition']['y1'] + $offsetY;
            
            $output .= $this->svg->createText(
                $titleX + (isset($xAxis['title']['offsetX']) ? $xAxis['title']['offsetX'] : 0),
                $titleY,
                $xAxis['title']['text'],
                [
                    'fontFamily' => isset($xAxis['title']['fontFamily']) ? $xAxis['title']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                    'fontSize' => isset($xAxis['title']['fontSize']) ? $xAxis['title']['fontSize'] : 14,
                    'fontWeight' => isset($xAxis['title']['fontWeight']) ? $xAxis['title']['fontWeight'] : 'bold',
                    'fill' => isset($xAxis['title']['color']) ? $xAxis['title']['color'] : '#333333',
                    'textAnchor' => 'middle'
                ]
            );
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Y-Achse
     * 
     * @param array $yAxis Y-Achsendefinition
     * @param int $id ID der Achse
     * @return string SVG-Elemente der Y-Achse
     */
    public function renderYAxis($yAxis, $id) {
        if (!isset($yAxis['enabled']) || !$yAxis['enabled']) {
            return '';
        }
        
        $output = '';
        
        // Achsenlinie rendern
        if (isset($yAxis['line']) && isset($yAxis['line']['enabled']) && $yAxis['line']['enabled']) {
            $output .= $this->svg->createLine(
                $yAxis['axisPosition']['x1'],
                $yAxis['axisPosition']['y1'],
                $yAxis['axisPosition']['x2'],
                $yAxis['axisPosition']['y2'],
                [
                    'stroke' => isset($yAxis['line']['color']) ? $yAxis['line']['color'] : '#999999',
                    'strokeWidth' => isset($yAxis['line']['width']) ? $yAxis['line']['width'] : 1,
                    'strokeDasharray' => isset($yAxis['line']['dashArray']) ? $yAxis['line']['dashArray'] : ''
                ]
            );
        }
        
        // Ticks und Beschriftungen rendern
        if (isset($yAxis['ticks'])) {
            foreach ($yAxis['ticks'] as $tick) {
                // Tick-Linie rendern
                if (!isset($yAxis['ticks']['enabled']) || $yAxis['ticks']['enabled']) {
                    $tickSize = isset($yAxis['ticks']['size']) ? $yAxis['ticks']['size'] : 6;
                    $tickX1 = $yAxis['axisPosition']['x1'];
                    $tickX2 = isset($yAxis['position']) && $yAxis['position'] === 'left' ? 
                              $tickX1 - $tickSize : 
                              $tickX1 + $tickSize;
                    
                    $output .= $this->svg->createLine(
                        $tickX1,
                        $tick['position'],
                        $tickX2,
                        $tick['position'],
                        [
                            'stroke' => isset($yAxis['ticks']['color']) ? $yAxis['ticks']['color'] : '#999999',
                            'strokeWidth' => isset($yAxis['ticks']['width']) ? $yAxis['ticks']['width'] : 1
                        ]
                    );
                }
                
                // Beschriftung rendern
                if (isset($yAxis['labels']) && (!isset($yAxis['labels']['enabled']) || $yAxis['labels']['enabled'])) {
                    $tickSize = isset($yAxis['ticks']['size']) ? $yAxis['ticks']['size'] : 6;
                    
                    // Abstand für Y-Achsenbeschriftungen vergrößern
                    $labelOffset = $this->horizontalBars ? 8 : 15;
                    
                    // Label-Offset für Y-Achsen-Beschriftungen
                    $labelsOffsetX = isset($yAxis['labels']['offsetX']) ? $yAxis['labels']['offsetX'] : 0;
                    $labelsOffsetY = isset($yAxis['labels']['offsetY']) ? $yAxis['labels']['offsetY'] : 0;
                    
                    $labelX = isset($yAxis['position']) && $yAxis['position'] === 'left' ? 
                              $yAxis['axisPosition']['x1'] - $tickSize - $labelOffset + $labelsOffsetX : 
                              $yAxis['axisPosition']['x1'] + $tickSize + $labelOffset + $labelsOffsetX;
                    
                    $textAnchor = isset($yAxis['position']) && $yAxis['position'] === 'left' ? 'end' : 'start';
                    if (isset($yAxis['labels']['align'])) {
                        $textAnchor = $yAxis['labels']['align'];
                    }
                    
                    // Bei horizontalen Balken die Rotation standardmäßig auf 0° setzen (keine Rotation)
                    $rotation = isset($yAxis['labels']['rotation']) ? $yAxis['labels']['rotation'] : 0;
                    
                    $labelY = $tick['position'] + $labelsOffsetY;
                    
                    $output .= $this->svg->createText(
                        $labelX,
                        $labelY,
                        $tick['label'],
                        [
                            'fontFamily' => isset($yAxis['labels']['fontFamily']) ? $yAxis['labels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                            'fontSize' => isset($yAxis['labels']['fontSize']) ? $yAxis['labels']['fontSize'] : 12,
                            'fontWeight' => isset($yAxis['labels']['fontWeight']) ? $yAxis['labels']['fontWeight'] : 'normal',
                            'fill' => isset($yAxis['labels']['color']) ? $yAxis['labels']['color'] : '#333333',
                            'textAnchor' => $textAnchor,
                            'dominantBaseline' => 'middle',
                            'rotate' => $rotation
                        ]
                    );
                }
            }
        }
        
        // Achsentitel rendern
        if (isset($yAxis['title']) && isset($yAxis['title']['enabled']) && $yAxis['title']['enabled'] && isset($yAxis['title']['text']) && $yAxis['title']['text'] !== '') {
            $titleY = $yAxis['axisPosition']['y1'] + ($yAxis['axisPosition']['y2'] - $yAxis['axisPosition']['y1']) / 2;
            
            $offsetX = isset($yAxis['title']['offsetX']) ? $yAxis['title']['offsetX'] : -35;
            $titleX = isset($yAxis['position']) && $yAxis['position'] === 'left' ?
                      $yAxis['axisPosition']['x1'] + $offsetX :
                      $yAxis['axisPosition']['x1'] + $offsetX;
                      
            // Rotation für den Titel festlegen - bei horizontalen Balken 0° statt -90°
            $rotation = isset($yAxis['title']['rotation']) ? $yAxis['title']['rotation'] : ($this->horizontalBars ? 0 : -90);
            
            $output .= $this->svg->createText(
                $titleX,
                $titleY + (isset($yAxis['title']['offsetY']) ? $yAxis['title']['offsetY'] : 0),
                $yAxis['title']['text'],
                [
                    'fontFamily' => isset($yAxis['title']['fontFamily']) ? $yAxis['title']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                    'fontSize' => isset($yAxis['title']['fontSize']) ? $yAxis['title']['fontSize'] : 14,
                    'fontWeight' => isset($yAxis['title']['fontWeight']) ? $yAxis['title']['fontWeight'] : 'bold',
                    'fill' => isset($yAxis['title']['color']) ? $yAxis['title']['color'] : '#333333',
                    'textAnchor' => 'middle',
                    'rotate' => $rotation
                ]
            );
        }
        
        return $output;
    }
    
    /**
     * Konvertiert einen X-Wert in eine X-Koordinate
     * 
     * @param mixed $value X-Wert
     * @param array $xAxis X-Achsendefinition
     * @param array $chartArea Daten zum Zeichenbereich
     * @return float X-Koordinate
     */
    public function convertXValueToCoordinate($value, $xAxis, $chartArea) {
        $type = isset($xAxis['type']) ? $xAxis['type'] : 'category';
        
        // Stelle sicher, dass $value bei kategorialen Achsen ein numerischer Wert ist
        if ($type === 'category' && !is_numeric($value)) {
            if (is_string($value) && isset($xAxis['categories'])) {
                // Wenn $value ein String ist und in den Kategorien gefunden wird, verwende den Index
                $index = array_search($value, $xAxis['categories']);
                if ($index !== false) {
                    $value = $index;
                } else {
                    // Wenn nicht gefunden, verwende 0 als Fallback
                    $value = 0;
                }
            } else {
                // Wenn $value nicht in eine Zahl umgewandelt werden kann, verwende 0 als Fallback
                $value = 0;
            }
        }
        
        // Berücksichtige benutzerdefinierte Offsets, wenn vorhanden
        $offsetX = isset($xAxis['offsetX']) ? $xAxis['offsetX'] : 0;
        
        switch ($type) {
            case 'category':
                // Bei Kategorieachse ist der Wert ein Index
                $categoryWidth = isset($xAxis['categoryWidth']) ? $xAxis['categoryWidth'] : 0;
                return $chartArea['x'] + ($value + 0.5) * $categoryWidth + $offsetX;
                
            case 'numeric':
                // Bei numerischer Achse ist der Wert eine Zahl
                $min = isset($xAxis['computedMin']) ? $xAxis['computedMin'] : 0;
                $scale = isset($xAxis['computedScale']) ? $xAxis['computedScale'] : 1;
                return $chartArea['x'] + ($value - $min) * $scale + $offsetX;
                
            case 'time':
                // Bei Zeitachse ist der Wert ein Zeitstempel
                $min = isset($xAxis['computedMin']) ? $xAxis['computedMin'] : 0;
                $scale = isset($xAxis['computedScale']) ? $xAxis['computedScale'] : 1;
                return $chartArea['x'] + ($value - $min) * $scale + $offsetX;
                
            case 'log':
                // Bei logarithmischer Achse ist der Wert eine Zahl
                $logMin = isset($xAxis['computedLogMin']) ? $xAxis['computedLogMin'] : 0;
                $scale = isset($xAxis['computedScale']) ? $xAxis['computedScale'] : 1;
                return $chartArea['x'] + (log10(max(0.000001, $value)) - $logMin) * $scale + $offsetX;
                
            case 'string':
                // Bei Stringachse ist der Wert ein Index
                $scale = isset($xAxis['computedScale']) ? $xAxis['computedScale'] : 1;
                return $chartArea['x'] + $value * $scale + $offsetX;
                
            default:
                return $chartArea['x'] + $offsetX;
        }
    }
    
    /**
     * Konvertiert einen Y-Wert in eine Y-Koordinate
     * 
     * @param mixed $value Y-Wert
     * @param array $yAxis Y-Achsendefinition
     * @param array $chartArea Daten zum Zeichenbereich
     * @return float Y-Koordinate
     */
    public function convertYValueToCoordinate($value, $yAxis, $chartArea) {
        $type = isset($yAxis['type']) ? $yAxis['type'] : 'numeric';
        
        // Stelle sicher, dass $value bei kategorialen Achsen ein numerischer Wert ist
        if ($type === 'category' && !is_numeric($value)) {
            if (is_string($value) && isset($yAxis['categories'])) {
                // Wenn $value ein String ist und in den Kategorien gefunden wird, verwende den Index
                $index = array_search($value, $yAxis['categories']);
                if ($index !== false) {
                    $value = $index;
                } else {
                    // Wenn nicht gefunden, verwende 0 als Fallback
                    $value = 0;
                }
            } else {
                // Wenn $value nicht in eine Zahl umgewandelt werden kann, verwende 0 als Fallback
                $value = 0;
            }
        }
        
        // Berücksichtige benutzerdefinierte Offsets, wenn vorhanden
        $offsetY = isset($yAxis['offsetY']) ? $yAxis['offsetY'] : 0;
        
        switch ($type) {
            case 'category':
                // Bei Kategorieachse ist der Wert ein Index
                $categoryHeight = isset($yAxis['categoryHeight']) ? $yAxis['categoryHeight'] : 0;
                // Verhindere Division durch Null
                if ($categoryHeight <= 0) {
                    $categoryHeight = $chartArea['height'];
                    if (isset($yAxis['categoriesCount']) && $yAxis['categoriesCount'] > 0) {
                        $categoryHeight = $chartArea['height'] / $yAxis['categoriesCount'];
                    }
                }
                return $chartArea['y'] + ($value + 0.5) * $categoryHeight + $offsetY;
                
            case 'numeric':
                // Bei numerischer Achse ist der Wert eine Zahl
                $min = isset($yAxis['computedMin']) ? $yAxis['computedMin'] : 0;
                $scale = isset($yAxis['computedScale']) ? $yAxis['computedScale'] : 1;
                // Y-Koordinate ist invertiert
                return $chartArea['y'] + $chartArea['height'] - ($value - $min) * $scale + $offsetY;
                
            case 'log':
                // Bei logarithmischer Achse ist der Wert eine Zahl
                $logMin = isset($yAxis['computedLogMin']) ? $yAxis['computedLogMin'] : 0;
                $scale = isset($yAxis['computedScale']) ? $yAxis['computedScale'] : 1;
                // Y-Koordinate ist invertiert
                return $chartArea['y'] + $chartArea['height'] - (log10(max(0.000001, $value)) - $logMin) * $scale + $offsetY;
                
            default:
                return $chartArea['y'] + $chartArea['height'] + $offsetY;
        }
    }
}