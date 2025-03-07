# PHPChart-Dokumentation

## Inhaltsverzeichnis

- [Einleitung](#einleitung)
- [Installation](#installation)
- [Allgemeine Konfiguration](#allgemeine-konfiguration)
- [Achsenkonfiguration](#achsenkonfiguration)
- [Legendenkonfiguration](#legendenkonfiguration)
- [Chart-Typen](#chart-typen)
  - [Bar-Charts](#bar-charts)
  - [Line-Charts](#line-charts)
  - [Area-Charts](#area-charts)
  - [Pie- und Donut-Charts](#pie-und-donut-charts)
  - [Multi-Pie-Charts](#multi-pie-charts)
  - [Scatter-Charts](#scatter-charts)
  - [Bubble-Charts](#bubble-charts)
  - [Polar-Charts](#polar-charts)
  - [Radar-Charts](#radar-charts)
  - [Waterfall-Charts](#waterfall-charts)
- [Fortgeschrittene Funktionen](#fortgeschrittene-funktionen)

## Einleitung

PHPChart ist eine umfassende PHP-Bibliothek zur Erzeugung von SVG-basierten Diagrammen. Sie unterstützt verschiedene Diagrammtypen, flexible Achsenkonfigurationen und umfangreiche Anpassungsoptionen.

## Installation

Kopieren Sie alle chart.class*.php Dateien und PHPChart.php in Ihr Projekt und binden Sie PHPChart.php ein:

```php
require_once('PHPChart.php');
```

## Allgemeine Konfiguration

Die grundlegende Konfiguration erfolgt beim Erstellen eines PHPChart-Objekts:

```php
$chart = new PHPChart([
    'width' => 800,             // Breite des Diagramms
    'height' => 500,            // Höhe des Diagramms
    'margin' => [               // Ränder
        'top' => 50,
        'right' => 50,
        'bottom' => 50,
        'left' => 50
    ],
    'background' => [           // Hintergrund
        'enabled' => true,
        'color' => '#ffffff',
        'borderRadius' => 0
    ],
    'grid' => [                 // Gitternetzlinien
        'enabled' => true,
        'color' => '#e0e0e0',
        'width' => 1,
        'dashArray' => ''
    ],
    'title' => [                // Titel
        'text' => 'Mein Diagramm',
        'enabled' => true,
        'fontFamily' => 'Arial, Helvetica, sans-serif',
        'fontSize' => 18,
        'fontWeight' => 'bold',
        'color' => '#333333',
        'align' => 'center',
        'offsetX' => 0,
        'offsetY' => 0
    ]
]);
```

## Achsenkonfiguration

### X-Achse

```php
$chart->addXAxis([
    'position' => 'bottom',     // bottom, top
    'title' => [
        'text' => 'X-Achsentitel',
        'enabled' => true,
        'color' => '#333333'
    ],
    'type' => 'category',       // category, numeric, time, log, string
    'categories' => ['A', 'B', 'C'], // Nur für Type 'category'
    'dateFormat' => 'd/m/Y',    // Nur für Type 'time'
    'min' => null,              // Minimum (null = automatisch)
    'max' => null,              // Maximum (null = automatisch)
    'labels' => [
        'enabled' => true,
        'fontFamily' => 'Arial',
        'fontSize' => 12,
        'color' => '#333333',
        'rotation' => 0,
        'format' => '{value}'
    ],
    'grid' => [
        'enabled' => true,
        'color' => '#e0e0e0'
    ]
]);
```

### Y-Achse

```php
$chart->addYAxis([
    'position' => 'left',       // left, right
    'title' => [
        'text' => 'Y-Achsentitel',
        'enabled' => true,
        'color' => '#333333'
    ],
    'type' => 'numeric',        // numeric, log
    'min' => null,              // Minimum (null = automatisch)
    'max' => null,              // Maximum (null = automatisch)
    'labels' => [
        'enabled' => true,
        'format' => '{value}',
        'prefix' => '',
        'suffix' => ' €',
        'decimals' => 2
    ],
    'grid' => [
        'enabled' => true,
        'color' => '#e0e0e0'
    ]
]);
```

## Legendenkonfiguration

```php
$chart->setLegendOptions([
    'enabled' => true,
    'position' => 'bottom',     // bottom, top, left, right, custom
    'align' => 'center',        // left, center, right
    'layout' => 'horizontal',   // horizontal, vertical
    'fontFamily' => 'Arial',
    'fontSize' => 12,
    'color' => '#333333',
    'background' => '#ffffff',  // Hintergrundfarbe
    'borderRadius' => 0,
    'border' => [
        'enabled' => false,
        'color' => '#cccccc',
        'width' => 1
    ]
]);
```

## Chart-Typen

### Bar-Charts

```php
// Vertikaler Bar-Chart
$chart->addYValues($werte, 'Serie1', [
    'type' => 'bar',
    'color' => '#5BC9AD',
    'bar' => [
        'width' => 20,          // Breite in Pixel (null = automatisch)
        'cornerRadius' => 5,    // Eckenradius
        'horizontal' => false   // Vertikale Balken
    ],
    'dataLabels' => [
        'enabled' => true,
        'format' => '{y}'       // Wertbeschriftung
    ]
]);

// Horizontaler Bar-Chart
$chart->addYValues($werte, 'Serie2', [
    'type' => 'bar',
    'color' => '#DC5244',
    'bar' => [
        'width' => 20,
        'horizontal' => true    // Horizontale Balken
    ]
]);

// Gestapelter Bar-Chart
$chart->addYValues($werte1, 'Gruppe1', [
    'type' => 'bar',
    'color' => '#5BC9AD',
    'stacked' => true,
    'stackGroup' => 'stack1'    // Stapelgruppe
]);

$chart->addYValues($werte2, 'Gruppe2', [
    'type' => 'bar',
    'color' => '#DC5244',
    'stacked' => true,
    'stackGroup' => 'stack1'    // Gleiche Stapelgruppe
]);
```

Optionen für Bar-Charts:
- `bar.width`: Balkenbreite (null = automatisch)
- `bar.maxWidth`: Maximale Balkenbreite
- `bar.cornerRadius`: Eckenradius der Balken
- `bar.columnSpacing`: Abstand zwischen Balken (0-1)
- `bar.horizontal`: `true` für horizontale Balken
- `stacked`: `true` für gestapelte Balken
- `stackGroup`: Name der Stapelgruppe

### Line-Charts

```php
// Einfacher Line-Chart
$chart->addYValues($werte, 'Linie1', [
    'type' => 'line',
    'color' => '#5BC9AD',
    'line' => [
        'width' => 2,
        'dashArray' => '',      // Leer für durchgezogene Linie
        'connectNulls' => false // Null-Werte überspringen
    ],
    'point' => [
        'enabled' => true,      // Punkte anzeigen
        'size' => 6,
        'shape' => 'circle',    // circle, square, triangle, diamond
        'color' => '#5BC9AD'
    ]
]);

// Stepped Line-Chart
$chart->addYValues($werte, 'StufenLinie', [
    'type' => 'line',
    'color' => '#DC5244',
    'line' => [
        'width' => 2,
        'stepped' => true       // Stufenlinie
    ]
]);

// Spline-Chart (glatte Kurve)
$chart->addYValues($werte, 'Spline', [
    'type' => 'spline',
    'color' => '#468DF3',
    'line' => [
        'width' => 2,
        'tension' => 0.5        // Krümmungsstärke (0-1)
    ]
]);
```

Optionen für Line-Charts:
- `type`: 'line' oder 'spline' (glatte Kurve)
- `line.width`: Linienbreite
- `line.dashArray`: Strichmuster (z.B. '5,5' für gestrichelt)
- `line.stepped`: `true` für Stufenlinie
- `line.connectNulls`: `true` um Null-Werte zu verbinden
- `line.tension`: Krümmungsstärke für Splines (0-1)
- `point`: Konfiguration der Datenpunkte

### Area-Charts

```php
// Einfacher Area-Chart
$chart->addYValues($werte, 'Fläche1', [
    'type' => 'area',
    'color' => '#5BC9AD',
    'area' => [
        'fillOpacity' => 0.4,   // Transparenz der Füllung
        'strokeWidth' => 2      // Linienbreite der Kontur
    ],
    'gradient' => [
        'enabled' => true,
        'colors' => ['#5BC9AD', '#ffffff'],
        'type' => 'linear',
        'angle' => 90           // Vertikal (0 = horizontal)
    ]
]);

// Gestapelter Area-Chart
$chart->addYValues($werte1, 'Fläche1', [
    'type' => 'area',
    'color' => '#5BC9AD',
    'stacked' => true,
    'stackGroup' => 'stack1'
]);

$chart->addYValues($werte2, 'Fläche2', [
    'type' => 'area',
    'color' => '#DC5244',
    'stacked' => true,
    'stackGroup' => 'stack1'
]);
```

Optionen für Area-Charts:
- `area.enabled`: `true` um Fläche anzuzeigen
- `area.fillOpacity`: Transparenz der Füllung (0-1)
- `area.strokeWidth`: Breite der Konturlinie
- `stacked`: `true` für gestapelte Flächen
- `stackGroup`: Name der Stapelgruppe
- `gradient`: Konfiguration des Farbverlaufs

### Pie- und Donut-Charts

```php
// Pie-Chart
$chart->addXValues(['A', 'B', 'C', 'D'], 'categories');
$chart->addYValues([25, 30, 15, 30], 'Pie1', [
    'type' => 'pie',
    'pie' => [
        'radius' => 150,                // Radius in Pixel
        'startAngle' => 0,              // 0° = Westen (links)
        'endAngle' => 360,              // Vollständiger Kreis
        'padAngle' => 0,                // Abstand zwischen Segmenten
        'cornerRadius' => 5             // Eckenradius der Segmente
    ],
    'dataLabels' => [
        'enabled' => true,
        'format' => '{percentage}%'     // Prozentanzeige
    ]
]);

// Donut-Chart
$chart->addYValues([25, 30, 15, 30], 'Donut1', [
    'type' => 'pie',
    'pie' => [
        'radius' => 150,
        'innerRadius' => 80,            // > 0 für Donut-Chart
        'centerX' => 400,               // X-Position des Zentrums
        'centerY' => 250                // Y-Position des Zentrums
    ]
]);
```

Optionen für Pie-/Donut-Charts:
- `pie.radius`: Radius (null = automatisch)
- `pie.innerRadius`: Innerer Radius (0 = Pie, >0 = Donut)
- `pie.startAngle`: Startwinkel in Grad (0° = Westen)
- `pie.endAngle`: Endwinkel in Grad
- `pie.padAngle`: Abstand zwischen Segmenten
- `pie.cornerRadius`: Eckenradius der Segmente
- `pie.centerX/centerY`: Position des Zentrums
- `pie.colors`: Array mit Farben für Segmente

### Multi-Pie-Charts

```php
// Multi-Pie mit zwei Diagrammen
$chart->addYValues([40, 60], 'Umsatz', [
    'type' => 'multipie',
    'multipie' => [
        'group' => 'Statistik',
        'title' => 'Umsatzverteilung',
        'ringPosition' => 0     // Innerer Ring
    ]
]);

$chart->addYValues([30, 45, 25], 'Kosten', [
    'type' => 'multipie',
    'multipie' => [
        'group' => 'Statistik',
        'title' => 'Kostenverteilung',
        'ringPosition' => 1     // Äußerer Ring
    ]
]);
```

Optionen für Multi-Pie-Charts:
- `multipie.group`: Gruppenname für zugehörige Diagramme
- `multipie.type`: 'multipie' oder 'multidonut'
- `multipie.title`: Titel für das einzelne Diagramm
- `multipie.ringPosition`: Position des Rings (0 = innerster)
- `multipie.layout`: 'auto' oder 'custom'
- `multipie.position`: Benutzerdefinierte Position {x, y, width, height}

### Scatter-Charts

```php
// Scatter-Chart
$chart->addXValues([1, 2, 3, 4, 5, 6, 7, 8, 9]);
$chart->addYValues([5, 7, 3, 8, 2, 6, 9, 4, 5], 'Punkte', [
    'type' => 'scatter',
    'color' => '#5BC9AD',
    'connectPoints' => false,       // Punkte verbinden
    'point' => [
        'enabled' => true,
        'size' => 8,
        'shape' => 'circle',        // circle, square, triangle, diamond
        'borderColor' => '#333333',
        'borderWidth' => 1
    ],
    'dataLabels' => [
        'enabled' => true,
        'format' => '{y}'
    ]
]);

// Scatter mit individuellen Punkten
$chart->addYValues([5, 7, 3, 8, 2], 'CustomPunkte', [
    'type' => 'scatter',
    'points' => [
        0 => ['size' => 10, 'color' => '#5BC9AD', 'shape' => 'circle'],
        1 => ['size' => 15, 'color' => '#DC5244', 'shape' => 'square'],
        2 => ['size' => 12, 'color' => '#468DF3', 'shape' => 'triangle'],
        3 => ['size' => 8,  'color' => '#A0A0A0', 'shape' => 'diamond'],
        4 => ['size' => 14, 'color' => '#DDDDDD', 'shape' => 'circle']
    ]
]);
```

Optionen für Scatter-Charts:
- `connectPoints`: `true` um Punkte mit Linien zu verbinden
- `lineColor/lineWidth`: Darstellung der Verbindungslinien
- `point`: Konfiguration der Datenpunkte
- `points`: Array mit individuellen Punktkonfigurationen
- `dataLabels`: Konfiguration der Datenbeschriftungen

### Bubble-Charts

```php
// Bubble-Chart (X-, Y-Werte und Größe)
$chart->addXValues([1, 2, 3, 4, 5]);
$chart->addYValues([5, 7, 3, 8, 2], 'Bubbles', [
    'type' => 'bubble',
    'color' => '#5BC9AD',
    'size' => [10, 25, 15, 20, 30],    // Größenwerte
    'bubble' => [
        'minSize' => 5,                // Minimale Blasengröße
        'maxSize' => 30,               // Maximale Blasengröße
        'sizeField' => 'size'          // Name des Größenfelds
    ],
    'dataLabels' => [
        'enabled' => true,
        'format' => '{z}'              // z = Größenwert
    ]
]);

// Bubble-Chart mit individuellen Blasen
$chart->addYValues([5, 7, 3, 8, 2], 'CustomBubbles', [
    'type' => 'bubble',
    'bubbles' => [
        0 => ['color' => '#5BC9AD', 'size' => 15],
        1 => ['color' => '#DC5244', 'size' => 25, 'borderColor' => '#333333'],
        2 => ['color' => '#468DF3', 'size' => 20],
        3 => ['color' => '#A0A0A0', 'size' => 30],
        4 => ['color' => '#DDDDDD', 'size' => 10]
    ]
]);
```

Optionen für Bubble-Charts:
- `bubble.minSize`: Minimale Blasengröße
- `bubble.maxSize`: Maximale Blasengröße
- `bubble.sizeField`: Name des Felds für Größenwerte
- `size`: Array mit Größenwerten
- `bubbles`: Array mit individuellen Blasenkonfigurationen

### Polar-Charts

```php
// Polar-Chart
$chart->addXValues([0, 45, 90, 135, 180, 225, 270, 315]);
$chart->addYValues([5, 7, 3, 8, 2, 6, 4, 5], 'Polar', [
    'type' => 'polar',
    'color' => '#5BC9AD',
    'polar' => [
        'area' => [
            'enabled' => true,      // Fläche füllen
            'fillOpacity' => 0.4    // Transparenz
        ]
    ]
]);
```

Optionen für Polar-Charts:
- `polar.area.enabled`: `true` für Flächenfüllung
- `polar.area.fillOpacity`: Transparenz der Füllung (0-1)
- Es gelten viele Optionen von Line-Charts

### Radar-Charts

```php
// Radar-Chart
$chart->addXValues(['A', 'B', 'C', 'D', 'E', 'F', 'G']);
$chart->addYValues([70, 80, 60, 90, 75, 85, 65], 'Radar1', [
    'type' => 'radar',
    'color' => '#5BC9AD',
    'radar' => [
        'area' => [
            'enabled' => true,      // Fläche füllen
            'fillOpacity' => 0.4    // Transparenz
        ]
    ]
]);

// Gestapelter Radar-Chart
$chart->addYValues([40, 50, 30, 60, 45, 55, 35], 'Radar2', [
    'type' => 'radar',
    'color' => '#DC5244',
    'stacked' => true,
    'stackGroup' => 'stack1'
]);
```

Optionen für Radar-Charts:
- `radar.area.enabled`: `true` für Flächenfüllung
- `radar.area.fillOpacity`: Transparenz der Füllung (0-1)
- `stacked`: `true` für gestapelte Radar-Flächen
- `stackGroup`: Name der Stapelgruppe

### Waterfall-Charts

```php
// Waterfall-Chart
$chart->addYValues([100, 50, -30, 20, -15, 10], 'Waterfall', [
    'type' => 'waterfall',
    'waterfall' => [
        'barWidth' => 30,        // Balkenbreite
        'cornerRadius' => 5,     // Eckenradius
        'initialValue' => 0,     // Startwert
        'barTypes' => [         // Typ pro Balken
            'initial',          // Startbalken
            'positive',         // Positiver Balken
            'negative',         // Negativer Balken
            'positive',
            'negative',
            'total'             // Summenbalken
        ],
        'initialColor' => '#468DF3',  // Farbe Startbalken
        'positiveColor' => '#4CAF50', // Farbe positive Balken
        'negativeColor' => '#F44336', // Farbe negative Balken
        'totalColor' => '#2196F3',    // Farbe Summenbalken
        'connectors' => [             // Verbindungslinien
            'enabled' => true,
            'color' => '#999999',
            'width' => 1,
            'dashArray' => '5,3'
        ]
    ]
]);

// Horizontaler Waterfall-Chart
$chart->addYValues([100, 50, -30, 20, -15, 10], 'HorizWaterfall', [
    'type' => 'waterfall',
    'waterfall' => [
        'horizontal' => true     // Horizontale Darstellung
    ]
]);
```

Optionen für Waterfall-Charts:
- `waterfall.barWidth`: Balkenbreite
- `waterfall.cornerRadius`: Eckenradius
- `waterfall.initialValue`: Startwert
- `waterfall.barTypes`: Array mit Typen pro Balken
- `waterfall.*Color`: Farben für verschiedene Balkentypen
- `waterfall.connectors`: Konfiguration der Verbindungslinien
- `waterfall.horizontal`: `true` für horizontale Darstellung

## Fortgeschrittene Funktionen

### Gradienten

```php
'gradient' => [
    'enabled' => true,
    'colors' => ['#5BC9AD', '#ffffff'],  // Farbverlauf
    'stops' => ['0%', '100%'],           // Stopps
    'type' => 'linear',                  // linear, radial
    'angle' => 90                        // 0-360 Grad
]
```

### Datenlabels

```php
'dataLabels' => [
    'enabled' => true,
    'fontFamily' => 'Arial',
    'fontSize' => 12,
    'fontWeight' => 'normal',
    'color' => '#333333',
    'format' => '{y}',                   // {x}, {y}, {percentage}
    'offsetX' => 0,
    'offsetY' => -15,
    'rotation' => 0                      // 0-360 Grad
]
```

### Datenpunkte

```php
'point' => [
    'enabled' => true,
    'size' => 6,
    'shape' => 'circle',                // circle, square, triangle, diamond
    'color' => '#5BC9AD',
    'borderColor' => '#333333',
    'borderWidth' => 1
]
```

### Mehrere Achsen kombinieren

```php
// Erste Y-Achse (links)
$chart->addYAxis([
    'position' => 'left',
    'title' => ['text' => 'Umsatz (€)']
]);

// Zweite Y-Achse (rechts)
$chart->addYAxis([
    'position' => 'right',
    'title' => ['text' => 'Stückzahl']
]);

// Serie für erste Y-Achse
$chart->addYValues($umsatzWerte, 'Umsatz', [
    'type' => 'line',
    'yAxisId' => 0  // Erste Y-Achse
]);

// Serie für zweite Y-Achse
$chart->addYValues($stueckzahlWerte, 'Stückzahl', [
    'type' => 'column',
    'yAxisId' => 1  // Zweite Y-Achse
]);
```

### Chart-Typen kombinieren

```php
// Balken-Chart
$chart->addYValues($werte1, 'Umsatz', [
    'type' => 'bar',
    'color' => '#5BC9AD'
]);

// Linien-Chart in derselben Ansicht
$chart->addYValues($werte2, 'Trend', [
    'type' => 'line',
    'color' => '#DC5244'
]);

// Area-Chart in derselben Ansicht
$chart->addYValues($werte3, 'Bereich', [
    'type' => 'area',
    'color' => '#468DF3'
]);
```

### SVG ausgeben oder speichern

```php
// Diagramm generieren
$chart->generate();

// Im Browser anzeigen
$chart->display();

// Als String zurückgeben
$svg = $chart->getSVG();

// In Datei speichern
$chart->saveToFile('chart.svg');
```
