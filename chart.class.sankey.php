<?php
/**
 * ChartSankeyChart - Sankey-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Sankey-Diagrammen zuständig.
 * Sankey-Diagramme visualisieren Flüsse zwischen Knoten, wobei die Breite des Flusses
 * proportional zur Menge ist.
 * 
 * @version 1.2
 */
class ChartSankeyChart {
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
     * Rendert ein Sankey-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Sankey-Diagramm-Serien
     * @param array $xValues Array mit X-Werten (Quell- und Zielknoten)
     * @param array $yValues Array mit Y-Werten (Flusswerte)
     * @param array $axes Achsendefinitionen (nicht vollständig verwendet bei Sankey-Diagrammen)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Sankey-Diagramms
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
        
        // Rendere jede Serie
        foreach ($updatedSeriesGroup as $seriesName => $seriesOptions) {
            $output .= $this->renderSankeySeries(
                $seriesName,
                $seriesOptions,
                $xValues,
                $yValues,
                $chartArea,
                $config
            );
        }
        
        return $output;
    }
    
    /**
     * Erstellt Gradienten für alle Serien, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Sankey-Diagramm-Serien
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
            
            // Individuelle Link-Gradienten prüfen
            if (isset($seriesOptions['links']) && is_array($seriesOptions['links'])) {
                foreach ($seriesOptions['links'] as $index => $linkOptions) {
                    // Prüfen, ob der Link einen Gradienten hat
                    if (isset($linkOptions['gradient']) && isset($linkOptions['gradient']['enabled']) && $linkOptions['gradient']['enabled']) {
                        $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                        $gradientId = 'gradient_' . $safeSeriesName . '_link_' . $index;
                        
                        // Speichere Gradientendefinition im Cache
                        $cacheKey = $seriesName . '_link_' . $index;
                        $this->gradientCache[$cacheKey] = [
                            'id' => $gradientId,
                            'options' => $linkOptions['gradient'],
                            'color' => isset($linkOptions['color']) ? $linkOptions['color'] : '#000000'
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
            if (strpos($key, '_link_') !== false) {
                // Individueller Link nach Index
                list($seriesName, $rest) = explode('_link_', $key);
                $index = intval($rest);
                
                if (isset($updatedSeriesGroup[$seriesName]) && 
                    isset($updatedSeriesGroup[$seriesName]['links']) && 
                    isset($updatedSeriesGroup[$seriesName]['links'][$index])) {
                    $updatedSeriesGroup[$seriesName]['links'][$index]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
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
     * Rendert eine Sankey-Serie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten (Quell- und Zielknoten)
     * @param array $yValues Array mit Y-Werten (Flusswerte)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente der Sankey-Serie
     */
    private function renderSankeySeries($seriesName, $seriesOptions, $xValues, $yValues, $chartArea, $config) {
        // Initialisiere Ausgabe
        $output = '';
        
        // Hole die Daten für diese Serie
        $nodes = isset($seriesOptions['sankey']['nodes']) ? $seriesOptions['sankey']['nodes'] : [];
        $links = isset($seriesOptions['sankey']['links']) ? $seriesOptions['sankey']['links'] : [];
        
        // Wenn keine Knoten oder Links definiert sind, versuche sie aus x- und y-Werten zu erstellen
        if (empty($nodes) || empty($links)) {
            $linkData = $this->extractLinksFromValues($seriesName, $xValues, $yValues);
            $nodes = $linkData['nodes'];
            $links = $linkData['links'];
        }
        
        // Wenn immer noch keine Daten vorhanden, nichts rendern
        if (empty($nodes) || empty($links)) {
            return $output;
        }
        
        // Berechne die Position der Knoten
        $nodeLayout = $this->calculateNodeLayout($nodes, $links, $chartArea, $seriesOptions);
        
        // Rendere die Links
        $output .= $this->renderLinks($links, $nodeLayout, $seriesOptions, $chartArea);
        
        // Rendere die Knoten
        $output .= $this->renderNodes($nodes, $nodeLayout, $seriesOptions, $chartArea);
        
        return $output;
    }
    
    /**
     * Extrahiert Knoten und Verbindungen aus X- und Y-Werten
     * 
     * @param string $seriesName Name der Serie
     * @param array $xValues Array mit X-Werten (Quell- und Zielknoten)
     * @param array $yValues Array mit Y-Werten (Flusswerte)
     * @return array Array mit Knoten und Links
     */
    private function extractLinksFromValues($seriesName, $xValues, $yValues) {
        $result = [
            'nodes' => [],
            'links' => []
        ];
        
        // Hole die Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : [];
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine Werte vorhanden sind, leeres Ergebnis zurückgeben
        if (empty($seriesX) || empty($seriesY)) {
            return $result;
        }
        
        // Knoten-Sammlung für eindeutige Knoten
        $uniqueNodes = [];
        
        // Links erstellen und eindeutige Knoten sammeln
        for ($i = 0; $i < count($seriesX); $i++) {
            if (!isset($seriesX[$i]) || !isset($seriesY[$i])) continue;
            
            // X-Wert enthält Quell- und Zielknoten
            $sourceTarget = $seriesX[$i];
            $value = $seriesY[$i];
            
            // Prüfe, ob der X-Wert ein Array oder ein String im Format "source->target" ist
            $source = '';
            $target = '';
            
            if (is_array($sourceTarget)) {
                $source = isset($sourceTarget['source']) ? $sourceTarget['source'] : '';
                $target = isset($sourceTarget['target']) ? $sourceTarget['target'] : '';
            } else if (is_string($sourceTarget) && strpos($sourceTarget, '->') !== false) {
                list($source, $target) = explode('->', $sourceTarget);
            }
            
            // Wenn Quelle oder Ziel leer ist, überspringe diesen Eintrag
            if (empty($source) || empty($target)) continue;
            
            // Füge Quelle und Ziel zu eindeutigen Knoten hinzu
            if (!isset($uniqueNodes[$source])) {
                $uniqueNodes[$source] = [
                    'id' => $source,
                    'name' => $source
                ];
            }
            
            if (!isset($uniqueNodes[$target])) {
                $uniqueNodes[$target] = [
                    'id' => $target,
                    'name' => $target
                ];
            }
            
            // Erstelle Link
            $result['links'][] = [
                'source' => $source,
                'target' => $target,
                'value' => $value
            ];
        }
        
        // Konvertiere eindeutige Knoten in ein Array
        $result['nodes'] = array_values($uniqueNodes);
        
        return $result;
    }
    
    /**
     * Berechnet das Layout der Knoten für ein Sankey-Diagramm
     * 
     * @param array $nodes Knoten im Diagramm
     * @param array $links Verbindungen zwischen Knoten
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $seriesOptions Optionen für die Serie
     * @return array Layout-Informationen für jeden Knoten
     */
    private function calculateNodeLayout($nodes, $links, $chartArea, $seriesOptions) {
        $nodeLayout = [];
        
        // Bestimme die Ebenen der Knoten
        $nodeLevels = $this->determineNodeLevels($nodes, $links);
        $maxLevel = max(array_values($nodeLevels));
        
        // Summe der Flusswerte für jeden Knoten berechnen (eingehend und ausgehend)
        $nodeValues = [];
        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            $nodeValues[$nodeId] = ['in' => 0, 'out' => 0, 'total' => 0];
        }
        
        // Berechne die Summen für jeden Knoten
        foreach ($links as $link) {
            $source = $link['source'];
            $target = $link['target'];
            $value = $link['value'];
            
            $nodeValues[$source]['out'] += $value;
            $nodeValues[$target]['in'] += $value;
        }
        
        // Berechne den Gesamtwert für jeden Knoten
        foreach ($nodeValues as $nodeId => &$value) {
            // Der Gesamtwert ist der größere der ein- und ausgehenden Werte
            $value['total'] = max($value['in'], $value['out']);
        }
        
        // Maximaler Wert für die Skalierung
        $maxValue = 0;
        foreach ($nodeValues as $nodeValue) {
            $maxValue = max($maxValue, $nodeValue['total']);
        }
        
        // Mindesthöhe und maximale Höhe für Knoten
        $minNodeHeight = isset($seriesOptions['sankey']['minNodeHeight']) ? 
                        $seriesOptions['sankey']['minNodeHeight'] : 5;
        $maxNodeHeight = isset($seriesOptions['sankey']['maxNodeHeight']) ? 
                        $seriesOptions['sankey']['maxNodeHeight'] : 50;
        
        // Knoten nach Ebenen gruppieren
        $nodesByLevel = [];
        foreach ($nodeLevels as $nodeId => $level) {
            if (!isset($nodesByLevel[$level])) {
                $nodesByLevel[$level] = [];
            }
            $nodesByLevel[$level][] = $nodeId;
        }
        
        // Abstand zwischen Ebenen
        $levelPadding = isset($seriesOptions['sankey']['levelPadding']) ? 
                       $seriesOptions['sankey']['levelPadding'] : 50;
        
        // Breite jeder Ebene
        $levelWidth = ($chartArea['width'] - ($maxLevel * $levelPadding)) / ($maxLevel + 1);
        
        // Höhe des Diagramms
        $diagramHeight = $chartArea['height'];
        
        // Berechne die vertikale Verteilung der Knoten pro Ebene
        foreach ($nodesByLevel as $level => $nodesInLevel) {
            // Sortiere Knoten nach Wertgröße (optional)
            usort($nodesInLevel, function($a, $b) use ($nodeValues) {
                return $nodeValues[$b]['total'] - $nodeValues[$a]['total'];
            });
            
            // Abstand zwischen Knoten
            $nodePadding = isset($seriesOptions['sankey']['nodePadding']) ? 
                          $seriesOptions['sankey']['nodePadding'] : 10;
            
            // Gesamthöhe aller Knoten in dieser Ebene berechnen
            $totalNodeValuesInLevel = 0;
            foreach ($nodesInLevel as $nodeId) {
                $totalNodeValuesInLevel += $nodeValues[$nodeId]['total'];
            }
            
            // Skalierungsfaktor berechnen
            $availableHeight = $diagramHeight - ($nodePadding * (count($nodesInLevel) - 1));
            $scale = $totalNodeValuesInLevel > 0 ? $availableHeight / $totalNodeValuesInLevel : 0;
            
            // Positioniere die Knoten in dieser Ebene
            $y = $chartArea['y'];
            $x = $chartArea['x'] + $level * ($levelWidth + $levelPadding);
            
            foreach ($nodesInLevel as $nodeId) {
                // Skaliere die Höhe proportional zum Wert
                $totalValue = $nodeValues[$nodeId]['total'];
                
                // Garantiere eine Mindesthöhe, aber begrenze auf die maximale Höhe
                $height = max($minNodeHeight, min($maxNodeHeight, $totalValue * $scale));
                
                // Speichere Layout-Informationen für diesen Knoten
                $nodeLayout[$nodeId] = [
                    'x' => $x,
                    'y' => $y,
                    'width' => $levelWidth,
                    'height' => $height,
                    'level' => $level,
                    'valueIn' => $nodeValues[$nodeId]['in'],
                    'valueOut' => $nodeValues[$nodeId]['out'],
                    'valueTotal' => $totalValue
                ];
                
                // Y-Position für den nächsten Knoten aktualisieren
                $y += $height + $nodePadding;
            }
        }
        
        return $nodeLayout;
    }
    
    /**
     * Bestimmt die Ebenen der Knoten basierend auf den Verbindungen
     * 
     * @param array $nodes Knoten im Diagramm
     * @param array $links Verbindungen zwischen Knoten
     * @return array Ebenen für jeden Knoten
     */
    private function determineNodeLevels($nodes, $links) {
        $levels = [];
        $connections = [];
        
        // Initialisiere Verbindungen für jeden Knoten
        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            $connections[$nodeId] = [
                'incoming' => [],
                'outgoing' => []
            ];
            $levels[$nodeId] = -1; // Noch nicht zugewiesen
        }
        
        // Sammle Verbindungen für jeden Knoten
        foreach ($links as $link) {
            $source = $link['source'];
            $target = $link['target'];
            
            $connections[$source]['outgoing'][] = $target;
            $connections[$target]['incoming'][] = $source;
        }
        
        // Finde Knoten ohne eingehende Verbindungen (Quellknoten)
        $sourceNodes = [];
        foreach ($connections as $nodeId => $connection) {
            if (empty($connection['incoming'])) {
                $sourceNodes[] = $nodeId;
                $levels[$nodeId] = 0; // Erste Ebene
            }
        }
        
        // Falls keine expliziten Quellknoten gefunden wurden, manuell zuweisen
        if (empty($sourceNodes)) {
            // Finde Knoten, die nur ausgehende Verbindungen haben oder die meisten ausgehenden Verbindungen
            $bestSource = null;
            $maxOutgoing = -1;
            
            foreach ($connections as $nodeId => $connection) {
                $outCount = count($connection['outgoing']);
                $inCount = count($connection['incoming']);
                
                if ($inCount == 0 && $outCount > 0) {
                    $sourceNodes[] = $nodeId;
                    $levels[$nodeId] = 0;
                } else if ($outCount > $inCount && $outCount > $maxOutgoing) {
                    $bestSource = $nodeId;
                    $maxOutgoing = $outCount;
                }
            }
            
            // Wenn immer noch keine Quelle gefunden wurde, verwende den besten Kandidaten
            if (empty($sourceNodes) && $bestSource !== null) {
                $sourceNodes[] = $bestSource;
                $levels[$bestSource] = 0;
            }
        }
        
        // Traversiere das Netzwerk, um Ebenen zuzuweisen
        $queue = $sourceNodes;
        while (!empty($queue)) {
            $nodeId = array_shift($queue);
            $currentLevel = $levels[$nodeId];
            
            // Aktualisiere die Ebenen für ausgehende Verbindungen
            foreach ($connections[$nodeId]['outgoing'] as $targetId) {
                $newLevel = $currentLevel + 1;
                
                // Aktualisiere die Ebene nur, wenn sie höher ist als die aktuelle
                if ($levels[$targetId] < $newLevel) {
                    $levels[$targetId] = $newLevel;
                    $queue[] = $targetId;
                }
            }
        }
        
        // Stelle sicher, dass alle Knoten eine Ebene haben
        foreach ($levels as $nodeId => $level) {
            if ($level == -1) {
                // Knoten ohne Ebene bekommen eine Standardebene
                $incomingLevels = [];
                foreach ($connections[$nodeId]['incoming'] as $sourceId) {
                    if (isset($levels[$sourceId]) && $levels[$sourceId] >= 0) {
                        $incomingLevels[] = $levels[$sourceId];
                    }
                }
                
                if (!empty($incomingLevels)) {
                    // Eine Ebene über dem Maximum der eingehenden Verbindungen
                    $levels[$nodeId] = max($incomingLevels) + 1;
                } else {
                    // Keine eingehenden Verbindungen mit Ebenen, setze auf 0
                    $levels[$nodeId] = 0;
                }
            }
        }
        
        return $levels;
    }
    
    /**
     * Skaliert einen Wert innerhalb eines Bereichs
     * 
     * @param float $value Zu skalierender Wert
     * @param float $minInput Minimaler Eingabewert
     * @param float $maxInput Maximaler Eingabewert
     * @param float $minOutput Minimaler Ausgabewert
     * @param float $maxOutput Maximaler Ausgabewert
     * @return float Skalierter Wert
     */
    private function scaleValue($value, $minInput, $maxInput, $minOutput, $maxOutput) {
        // Verhindere Division durch Null
        if ($maxInput == $minInput) {
            return $minOutput;
        }
        
        $ratio = ($value - $minInput) / ($maxInput - $minInput);
        return $minOutput + $ratio * ($maxOutput - $minOutput);
    }
    
    /**
     * Rendert die Knoten eines Sankey-Diagramms
     * 
     * @param array $nodes Knoten im Diagramm
     * @param array $nodeLayout Layout-Informationen für jeden Knoten
     * @param array $seriesOptions Optionen für die Serie
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Knoten
     */
    private function renderNodes($nodes, $nodeLayout, $seriesOptions, $chartArea) {
        $output = '';
        
        // Node-Optionen
        $nodeOpacity = isset($seriesOptions['sankey']['nodeOpacity']) ? 
                      $seriesOptions['sankey']['nodeOpacity'] : 0.8;
        $cornerRadius = isset($seriesOptions['sankey']['cornerRadius']) ? 
                       $seriesOptions['sankey']['cornerRadius'] : 3;
        $defaultNodeColor = isset($seriesOptions['sankey']['nodeColor']) ? 
                           $seriesOptions['sankey']['nodeColor'] : '#1f77b4';
        $strokeColor = isset($seriesOptions['sankey']['nodeStrokeColor']) ? 
                      $seriesOptions['sankey']['nodeStrokeColor'] : '#ffffff';
        $strokeWidth = isset($seriesOptions['sankey']['nodeStrokeWidth']) ? 
                      $seriesOptions['sankey']['nodeStrokeWidth'] : 1;
        
        // Benutzerdefinierte Knotenfarben
        $nodeColors = isset($seriesOptions['sankey']['nodeColors']) ? 
                     $seriesOptions['sankey']['nodeColors'] : [];
        
        // Rendere jeden Knoten
        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            
            // Prüfe, ob Layout-Informationen für diesen Knoten vorhanden sind
            if (!isset($nodeLayout[$nodeId])) continue;
            
            $layout = $nodeLayout[$nodeId];
            
            // Bestimme die Farbe für diesen Knoten
            $color = $defaultNodeColor;
            if (isset($nodeColors[$nodeId])) {
                $color = $nodeColors[$nodeId];
            } else if (isset($node['color'])) {
                $color = $node['color'];
            }
            
            // Rendere den Knoten
            $output .= $this->svg->createRect(
                $layout['x'],
                $layout['y'],
                $layout['width'],
                $layout['height'],
                [
                    'fill' => $color,
                    'fillOpacity' => $nodeOpacity,
                    'stroke' => $strokeColor,
                    'strokeWidth' => $strokeWidth,
                    'rx' => $cornerRadius,
                    'ry' => $cornerRadius
                ]
            );
            
            // Rendere den Knotennamen, falls aktiviert
            if (!isset($seriesOptions['sankey']['nodeLabels']) || 
                (isset($seriesOptions['sankey']['nodeLabels']['enabled']) && $seriesOptions['sankey']['nodeLabels']['enabled'])) {
                
                $labelOptions = isset($seriesOptions['sankey']['nodeLabels']) ? 
                              $seriesOptions['sankey']['nodeLabels'] : [];
                
                $fontSize = isset($labelOptions['fontSize']) ? $labelOptions['fontSize'] : 12;
                $fontColor = isset($labelOptions['color']) ? $labelOptions['color'] : '#333333';
                $fontFamily = isset($labelOptions['fontFamily']) ? $labelOptions['fontFamily'] : 'Arial, Helvetica, sans-serif';
                $fontWeight = isset($labelOptions['fontWeight']) ? $labelOptions['fontWeight'] : 'normal';
                $position = isset($labelOptions['position']) ? $labelOptions['position'] : 'inside';
                
                $nodeName = isset($node['name']) ? $node['name'] : $nodeId;
                
                if ($position === 'inside') {
                    // Label innerhalb des Knotens
                    $labelX = $layout['x'] + $layout['width'] / 2;
                    $labelY = $layout['y'] + $layout['height'] / 2;
                    $textAnchor = 'middle';
                } else if ($position === 'left') {
                    // Label links vom Knoten
                    $labelX = $layout['x'] - 5;
                    $labelY = $layout['y'] + $layout['height'] / 2;
                    $textAnchor = 'end';
                } else { // 'right'
                    // Label rechts vom Knoten
                    $labelX = $layout['x'] + $layout['width'] + 5;
                    $labelY = $layout['y'] + $layout['height'] / 2;
                    $textAnchor = 'start';
                }
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $nodeName,
                    [
                        'fontFamily' => $fontFamily,
                        'fontSize' => $fontSize,
                        'fontWeight' => $fontWeight,
                        'fill' => $fontColor,
                        'textAnchor' => $textAnchor,
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert die Verbindungen eines Sankey-Diagramms
     * 
     * @param array $links Verbindungen zwischen Knoten
     * @param array $nodeLayout Layout-Informationen für jeden Knoten
     * @param array $seriesOptions Optionen für die Serie
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Verbindungen
     */
    private function renderLinks($links, $nodeLayout, $seriesOptions, $chartArea) {
        $output = '';
        
        // Link-Optionen
        $linkOpacity = isset($seriesOptions['sankey']['linkOpacity']) ? 
                      $seriesOptions['sankey']['linkOpacity'] : 0.4;
        $curvature = isset($seriesOptions['sankey']['curvature']) ? 
                    $seriesOptions['sankey']['curvature'] : 0.5;
        $defaultLinkColor = isset($seriesOptions['sankey']['linkColor']) ? 
                           $seriesOptions['sankey']['linkColor'] : '#999999';
        
        // Benutzerdefinierte Verbindungsfarben
        $linkColors = isset($seriesOptions['sankey']['linkColors']) ? 
                     $seriesOptions['sankey']['linkColors'] : [];
        
        // Knotenfarben für Quellbasierte Link-Färbung
        $nodeColors = isset($seriesOptions['sankey']['nodeColors']) ? 
                     $seriesOptions['sankey']['nodeColors'] : [];
        
        // Berechne die Skalierung der Linkbreiten
        // Finde den maximalen Wert für die Skalierung
        $maxValue = 0;
        foreach ($links as $link) {
            $maxValue = max($maxValue, $link['value']);
        }
        
        // Vorbereitung für die Positionierung der Links
        $sourceNodeLinks = [];
        $targetNodeLinks = [];
        
        // Organisiere Links nach Quell- und Zielknoten
        foreach ($links as $i => $link) {
            $source = $link['source'];
            $target = $link['target'];
            $value = $link['value'];
            
            if (!isset($sourceNodeLinks[$source])) {
                $sourceNodeLinks[$source] = [];
            }
            if (!isset($targetNodeLinks[$target])) {
                $targetNodeLinks[$target] = [];
            }
            
            $sourceNodeLinks[$source][] = [
                'index' => $i,
                'target' => $target,
                'value' => $value
            ];
            
            $targetNodeLinks[$target][] = [
                'index' => $i,
                'source' => $source,
                'value' => $value
            ];
        }
        
        // Berechne die Y-Offsets für die Links
        $sourceOffsets = [];
        $targetOffsets = [];
        
        foreach ($nodeLayout as $nodeId => $layout) {
            $sourceOffsets[$nodeId] = $layout['y'];
            $targetOffsets[$nodeId] = $layout['y'];
        }
        
        // Rendere die Links
        foreach ($links as $index => $link) {
            $source = $link['source'];
            $target = $link['target'];
            $value = $link['value'];
            
            // Prüfe, ob Layout-Informationen für Quell- und Zielknoten vorhanden sind
            if (!isset($nodeLayout[$source]) || !isset($nodeLayout[$target])) {
                continue;
            }
            
            $sourceLayout = $nodeLayout[$source];
            $targetLayout = $nodeLayout[$target];
            
            // Berechne die Breite des Links proportional zum Wert
            // Skalierung basierend auf der Knotenhöhe und dem Wert
            $sourceScale = $sourceLayout['height'] / $sourceLayout['valueOut'];
            $targetScale = $targetLayout['height'] / $targetLayout['valueIn'];
            
            // Breite des Links an Quell- und Zielposition
            $sourceLinkWidth = $value * $sourceScale;
            $targetLinkWidth = $value * $targetScale;
            
            // Startposition der Verbindung am Quellknoten
            $sx = $sourceLayout['x'] + $sourceLayout['width'];
            $sy = $sourceOffsets[$source];
            
            // Endposition der Verbindung am Zielknoten
            $tx = $targetLayout['x'];
            $ty = $targetOffsets[$target];
            
            // Aktualisiere die Offsets für zukünftige Links
            $sourceOffsets[$source] += $sourceLinkWidth;
            $targetOffsets[$target] += $targetLinkWidth;
            
            // Bestimme die Farbe für diese Verbindung
            $color = $defaultLinkColor;
            
            // Benutzerdefinierte Farbe aus den Links-Optionen
            if (isset($linkColors[$source . '->' . $target])) {
                $color = $linkColors[$source . '->' . $target];
            } else if (isset($link['color'])) {
                $color = $link['color'];
            } else if (isset($nodeColors[$source])) {
                // Verwende die Quellknotenfarbe für den Link
                $color = $nodeColors[$source];
            }
            
            // Prüfe auf Gradient-ID
            $fillColor = $color;
            if (isset($link['gradientId'])) {
                $fillColor = $link['gradientId'];
            } else if (isset($seriesOptions['gradientId'])) {
                $fillColor = $seriesOptions['gradientId'];
            }
            
            // Kontrollpunkte für die Bézierkurve
            $cpx1 = $sx + ($tx - $sx) * $curvature;
            $cpy1 = $sy;
            $cpx2 = $tx - ($tx - $sx) * $curvature;
            $cpy2 = $ty;
            
            // Berechne zusätzliche Punkte für die untere Kurve
            $cpy2_plus_targetLinkWidth = $cpy2 + $targetLinkWidth;
            $cpy1_plus_sourceLinkWidth = $cpy1 + $sourceLinkWidth;
            $sy_plus_sourceLinkWidth = $sy + $sourceLinkWidth;
            
            // Rendere die Verbindung als Pfad
            $path = "M{$sx},{$sy} " .
                   "C{$cpx1},{$cpy1} {$cpx2},{$cpy2} {$tx},{$ty} " .
                   "v{$targetLinkWidth} " .
                   "C" . $cpx2 . "," . $cpy2_plus_targetLinkWidth . " " . 
                   $cpx1 . "," . $cpy1_plus_sourceLinkWidth . " " . 
                   $sx . "," . $sy_plus_sourceLinkWidth . " " .
                   "Z";
            
            $output .= $this->svg->createPath(
                $path,
                [
                    'fill' => $fillColor,
                    'fillOpacity' => $linkOpacity,
                    'stroke' => 'none'
                ]
            );
            
            // Rendere Wertbeschriftung, falls aktiviert
            if (isset($seriesOptions['sankey']['linkLabels']) && 
                isset($seriesOptions['sankey']['linkLabels']['enabled']) && 
                $seriesOptions['sankey']['linkLabels']['enabled']) {
                
                $labelOptions = $seriesOptions['sankey']['linkLabels'];
                
                $fontSize = isset($labelOptions['fontSize']) ? $labelOptions['fontSize'] : 10;
                $fontColor = isset($labelOptions['color']) ? $labelOptions['color'] : '#333333';
                $fontFamily = isset($labelOptions['fontFamily']) ? $labelOptions['fontFamily'] : 'Arial, Helvetica, sans-serif';
                $fontWeight = isset($labelOptions['fontWeight']) ? $labelOptions['fontWeight'] : 'normal';
                $format = isset($labelOptions['format']) ? $labelOptions['format'] : '{value}';
                
                // Position der Beschriftung in der Mitte der Verbindung
                $labelX = ($sx + $tx) / 2;
                $labelY = ($sy + $ty) / 2 + min($sourceLinkWidth, $targetLinkWidth) / 2;
                
                // Formatiere den Wert
                $labelText = str_replace('{value}', $this->utils->formatNumber($value), $format);
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $labelText,
                    [
                        'fontFamily' => $fontFamily,
                        'fontSize' => $fontSize,
                        'fontWeight' => $fontWeight,
                        'fill' => $fontColor,
                        'textAnchor' => 'middle',
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
        }
        
        return $output;
    }
}
?>