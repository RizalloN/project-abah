param(
    [Parameter(Mandatory = $true)][string]$TemplatePath,
    [Parameter(Mandatory = $true)][string]$DataPath,
    [Parameter(Mandatory = $true)][string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$culture = [System.Globalization.CultureInfo]::GetCultureInfo('id-ID')
$ppt = $null
$presentation = $null

function Get-Rgb([string]$Hex) {
    $value = $Hex.TrimStart('#')
    $r = [Convert]::ToInt32($value.Substring(0, 2), 16)
    $g = [Convert]::ToInt32($value.Substring(2, 2), 16)
    $b = [Convert]::ToInt32($value.Substring(4, 2), 16)
    return $r + ($g * 256) + ($b * 65536)
}

$colors = @{
    Blue = Get-Rgb '#00529C'
    Nusantara = Get-Rgb '#095AC8'
    Cakrawala = Get-Rgb '#307FE2'
    Mentari = Get-Rgb '#71C5E8'
    Orange = Get-Rgb '#F58220'
    Ink = Get-Rgb '#132238'
    Muted = Get-Rgb '#5B6677'
    Line = Get-Rgb '#D9E2EC'
    Soft = Get-Rgb '#F3F6F9'
    White = Get-Rgb '#FFFFFF'
    Green = Get-Rgb '#16794B'
    GreenBg = Get-Rgb '#E2F3E9'
    Red = Get-Rgb '#B4232E'
    RedBg = Get-Rgb '#FBE4E6'
    Yellow = Get-Rgb '#8A6500'
    YellowBg = Get-Rgb '#FFF2C7'
    Gray = Get-Rgb '#EEF1F4'
}

function Add-Text($Slide, [string]$Text, [double]$Left, [double]$Top, [double]$Width, [double]$Height, [double]$Size = 18, [int]$Color = $colors.Ink, [bool]$Bold = $false, [int]$Align = 1) {
    $shape = $Slide.Shapes.AddTextbox(1, $Left, $Top, $Width, $Height)
    $shape.TextFrame.MarginLeft = 2
    $shape.TextFrame.MarginRight = 2
    $shape.TextFrame.MarginTop = 1
    $shape.TextFrame.MarginBottom = 1
    $shape.TextFrame.WordWrap = -1
    $shape.TextFrame.AutoSize = 0
    $shape.TextFrame.TextRange.Text = $Text
    $shape.TextFrame.TextRange.Font.Name = 'Arial'
    $shape.TextFrame.TextRange.Font.Size = $Size
    $shape.TextFrame.TextRange.Font.Color.RGB = $Color
    $shape.TextFrame.TextRange.Font.Bold = $(if ($Bold) { -1 } else { 0 })
    $shape.TextFrame.TextRange.ParagraphFormat.Alignment = $Align
    # PowerPoint can shrink a textbox while its font is being applied. Reassert
    # the requested geometry so the editable PPTX remains stable across hosts.
    $shape.TextFrame.AutoSize = 0
    $shape.Left = $Left
    $shape.Top = $Top
    $shape.Width = $Width
    $shape.Height = $Height
    return $shape
}

function Add-Rect($Slide, [double]$Left, [double]$Top, [double]$Width, [double]$Height, [int]$Fill, [int]$Line = $colors.Line, [double]$Radius = 0) {
    $type = if ($Radius -gt 0) { 5 } else { 1 }
    $shape = $Slide.Shapes.AddShape($type, $Left, $Top, $Width, $Height)
    $shape.Fill.Solid()
    $shape.Fill.ForeColor.RGB = $Fill
    $shape.Line.ForeColor.RGB = $Line
    $shape.Line.Weight = 1
    return $shape
}

function Add-Line($Slide, [double]$X1, [double]$Y1, [double]$X2, [double]$Y2, [int]$Color = $colors.Line, [double]$Weight = 1.5) {
    $line = $Slide.Shapes.AddLine($X1, $Y1, $X2, $Y2)
    $line.Line.ForeColor.RGB = $Color
    $line.Line.Weight = $Weight
    return $line
}

function Add-Branding($Slide, [string]$Section, [int]$Number) {
    Add-Text $Slide $Section.ToUpperInvariant() 55 30 820 30 13 $colors.Cakrawala $true | Out-Null
    if (Test-Path $script:data.meta.danantara_logo) {
        $Slide.Shapes.AddPicture($script:data.meta.danantara_logo, 0, -1, 55, 62, 150, 55) | Out-Null
    }
    if (Test-Path $script:data.meta.bri_logo) {
        $Slide.Shapes.AddPicture($script:data.meta.bri_logo, 0, -1, 1215, 57, 165, 60) | Out-Null
    }
    Add-Line $Slide 55 124 1385 124 $colors.Mentari 3 | Out-Null
    Add-Text $Slide $script:data.meta.period_label 55 771 240 20 11 $colors.Muted $false | Out-Null
    Add-Text $Slide ([string]$Number) 1325 771 55 20 11 $colors.Blue $true 3 | Out-Null
}

function New-ContentSlide([string]$Section, [string]$Title, [string]$Subtitle = '') {
    $layout = $script:presentation.SlideMaster.CustomLayouts.Item(7)
    $slide = $script:presentation.Slides.AddSlide($script:presentation.Slides.Count + 1, $layout)
    $slide.FollowMasterBackground = 0
    $slide.Background.Fill.Solid()
    $slide.Background.Fill.ForeColor.RGB = $colors.White
    Add-Branding $slide $Section $slide.SlideIndex
    Add-Text $slide $Title 55 145 1120 50 31 $colors.Blue $true | Out-Null
    if ($Subtitle) {
        Add-Text $slide $Subtitle 55 194 1260 26 14 $colors.Muted $false | Out-Null
    }
    return $slide
}

function Format-Amount($Value) {
    if ($null -eq $Value) { return '-' }
    return ([double]$Value / 1000000).ToString('N0', $culture)
}

function Format-Delta($Value) {
    if ($null -eq $Value) { return '-' }
    $number = [double]$Value / 1000000
    if ([Math]::Abs($number) -lt 0.5) { return '0' }
    if ($number -lt 0) { return '(' + [Math]::Abs($number).ToString('N0', $culture) + ')' }
    return '+' + $number.ToString('N0', $culture)
}

function Format-Percent($Value) {
    if ($null -eq $Value) { return '-' }
    return ([double]$Value).ToString('N2', $culture) + '%'
}

function Format-CompactAmount($Value) {
    if ($null -eq $Value) { return '-' }
    $number = [double]$Value
    $absolute = [Math]::Abs($number)
    if ($absolute -ge 1000000000000) { return 'Rp' + ($number / 1000000000000).ToString('N2', $culture) + ' T' }
    if ($absolute -ge 1000000000) { return 'Rp' + ($number / 1000000000).ToString('N2', $culture) + ' M' }
    if ($absolute -ge 1000000) { return 'Rp' + ($number / 1000000).ToString('N2', $culture) + ' Jt' }
    return 'Rp' + $number.ToString('N0', $culture)
}

function Format-SeriesValue($Series, $Value) {
    if ([string]$Series.format -eq 'percent') {
        return Format-Percent $Value
    }
    return Format-CompactAmount ([double]$Value * 1000000)
}

function Format-SeriesDelta($Series, $Value) {
    $number = [double]$Value
    $prefix = if ($number -gt 0) { '+' } elseif ($number -lt 0) { '-' } else { '' }
    if ([string]$Series.format -eq 'percent') {
        return $prefix + (Format-Percent ([Math]::Abs($number)))
    }
    return $prefix + (Format-CompactAmount ([Math]::Abs($number) * 1000000))
}

function Set-TableCell($Table, [int]$Row, [int]$Column, [string]$Text, [int]$Fill, [int]$FontColor, [bool]$Bold = $false, [int]$Align = 1, [double]$FontSize = 12) {
    $shape = $Table.Cell($Row, $Column).Shape
    $shape.Fill.Solid()
    $shape.Fill.ForeColor.RGB = $Fill
    $shape.TextFrame.MarginLeft = 5
    $shape.TextFrame.MarginRight = 5
    $shape.TextFrame.MarginTop = 2
    $shape.TextFrame.MarginBottom = 2
    $shape.TextFrame.TextRange.Text = $Text
    $shape.TextFrame.TextRange.Font.Name = 'Arial'
    $shape.TextFrame.TextRange.Font.Size = $FontSize
    $shape.TextFrame.TextRange.Font.Color.RGB = $FontColor
    $shape.TextFrame.TextRange.Font.Bold = $(if ($Bold) { -1 } else { 0 })
    $shape.TextFrame.TextRange.ParagraphFormat.Alignment = $Align
}

function Get-DeltaStyle($Value, [bool]$Inverse = $false) {
    if ($null -eq $Value) { return @($colors.White, $colors.Muted) }
    $number = [double]$Value
    if ([Math]::Abs($number) -lt 0.00001) { return @($colors.YellowBg, $colors.Yellow) }
    $positive = $number -gt 0
    if ($Inverse) { $positive = -not $positive }
    if ($positive) { return @($colors.GreenBg, $colors.Green) }
    return @($colors.RedBg, $colors.Red)
}

function Add-PerformanceTable($Slide, $Rows, [bool]$Quality = $false, [double]$Top = 245) {
    $headers = @('UNIT / PRODUK', 'POSISI', '% AREA', 'YOY', 'YTD', 'MOM', 'MTD', 'DTD', 'RKA', 'PENC.')
    $tableShape = $Slide.Shapes.AddTable($Rows.Count + 1, $headers.Count, 55, $Top, 1330, 420)
    $table = $tableShape.Table
    $widths = @(250, 130, 100, 118, 118, 118, 118, 118, 130, 110)
    for ($c = 1; $c -le $headers.Count; $c++) {
        $table.Columns.Item($c).Width = $widths[$c - 1]
        Set-TableCell $table 1 $c $headers[$c - 1] $colors.Blue $colors.White $true 2 11
    }

    for ($r = 0; $r -lt $Rows.Count; $r++) {
        $row = $Rows[$r]
        $fill = if ($r -eq 0) { Get-Rgb '#EAF3FC' } elseif ($r % 2 -eq 0) { $colors.Soft } else { $colors.White }
        Set-TableCell $table ($r + 2) 1 ([string]$row.label) $fill $colors.Ink ($r -eq 0) 1 12
        Set-TableCell $table ($r + 2) 2 (Format-Amount $row.current) $fill $colors.Ink $true 3 12
        Set-TableCell $table ($r + 2) 3 (Format-Percent $row.share) $fill $colors.Muted $false 3 11
        $keys = @('yoy', 'ytd', 'mom', 'mtd', 'dtd')
        for ($i = 0; $i -lt $keys.Count; $i++) {
            $value = $row.deltas.($keys[$i])
            $style = Get-DeltaStyle $value $Quality
            Set-TableCell $table ($r + 2) ($i + 4) (Format-Delta $value) $style[0] $style[1] $true 3 11
        }
        Set-TableCell $table ($r + 2) 9 (Format-Amount $row.rka) $fill $colors.Ink $false 3 11
        $achievementStyle = Get-DeltaStyle $(if ($null -eq $row.achievement) { $null } else { [double]$row.achievement - 100 }) $Quality
        Set-TableCell $table ($r + 2) 10 (Format-Percent $row.achievement) $achievementStyle[0] $achievementStyle[1] $true 3 11
    }

    Add-Text $Slide 'Dalam Rp Juta. Warna delta: negatif merah, nol kuning, positif hijau.' 55 ($Top + 430) 760 22 11 $colors.Muted $false | Out-Null
}

function Add-BranchPerformanceOverview($Slide, $Row, [bool]$Quality = $false, [double]$Top = 245) {
    $gap = if ($null -eq $Row.rka) { $null } else { [double]$Row.current - [double]$Row.rka }
    $achievementDelta = if ($null -eq $Row.achievement) { $null } else { [double]$Row.achievement - 100 }
    $gapStyle = Get-DeltaStyle $gap $Quality
    $achievementStyle = Get-DeltaStyle $achievementDelta $Quality
    $kpis = @(
        @{ label = 'Posisi'; value = Format-Amount $Row.current; note = 'Rp Juta'; fill = $colors.Soft; color = $colors.Blue },
        @{ label = 'RKA'; value = Format-Amount $Row.rka; note = 'Rp Juta'; fill = $colors.Soft; color = $colors.Ink },
        @{ label = 'Gap terhadap RKA'; value = Format-Delta $gap; note = 'Rp Juta'; fill = $gapStyle[0]; color = $gapStyle[1] },
        @{ label = 'Pencapaian RKA'; value = Format-Percent $Row.achievement; note = ('Kontribusi area ' + (Format-Percent $Row.share)); fill = $achievementStyle[0]; color = $achievementStyle[1] }
    )

    for ($i = 0; $i -lt $kpis.Count; $i++) {
        $left = 55 + ($i * 337)
        Add-Rect $Slide $left $Top 317 108 $kpis[$i].fill $colors.Line 6 | Out-Null
        Add-Rect $Slide $left $Top 7 108 $kpis[$i].color $kpis[$i].color 0 | Out-Null
        Add-Text $Slide ([string]$kpis[$i].label) ($left + 20) ($Top + 14) 275 20 11 $colors.Muted $true | Out-Null
        Add-Text $Slide ([string]$kpis[$i].value) ($left + 20) ($Top + 39) 275 34 22 $kpis[$i].color $true | Out-Null
        Add-Text $Slide ([string]$kpis[$i].note) ($left + 20) ($Top + 79) 275 18 10 $colors.Muted $false | Out-Null
    }

    $deltaItems = @(
        @{ label = 'YoY'; value = $Row.deltas.yoy },
        @{ label = 'YtD'; value = $Row.deltas.ytd },
        @{ label = 'MoM'; value = $Row.deltas.mom },
        @{ label = 'MtD'; value = $Row.deltas.mtd },
        @{ label = 'DtD'; value = $Row.deltas.dtd }
    )
    Add-Text $Slide 'PERGERAKAN TERHADAP POSISI PEMBANDING' 55 ($Top + 132) 650 22 12 $colors.Blue $true | Out-Null
    for ($i = 0; $i -lt $deltaItems.Count; $i++) {
        $item = $deltaItems[$i]
        $left = 55 + ($i * 268)
        $style = Get-DeltaStyle $item.value $Quality
        Add-Rect $Slide $left ($Top + 162) 248 92 $style[0] $colors.Line 5 | Out-Null
        Add-Text $Slide ([string]$item.label) ($left + 16) ($Top + 176) 95 18 11 $colors.Muted $true | Out-Null
        Add-Text $Slide (Format-Delta $item.value) ($left + 16) ($Top + 202) 215 30 18 $style[1] $true | Out-Null
        Add-Text $Slide 'Rp Juta' ($left + 16) ($Top + 230) 120 16 9.5 $colors.Muted $false | Out-Null
    }

    $availableDeltas = @($deltaItems | Where-Object { $null -ne $_.value })
    $best = $availableDeltas | Sort-Object { [double]$_.value } -Descending | Select-Object -First 1
    $worst = $availableDeltas | Sort-Object { [double]$_.value } | Select-Object -First 1
    $gapNarrative = if ($null -eq $gap) {
        'RKA belum tersedia pada scope ini.'
    } elseif ($gap -ge 0) {
        'Posisi berada di atas RKA sebesar ' + (Format-Delta $gap) + ' Rp Juta.'
    } else {
        'Masih terdapat gap RKA sebesar ' + (Format-Delta $gap) + ' Rp Juta.'
    }
    $momentumNarrative = if ($null -eq $best -or $null -eq $worst) {
        'Data pembanding belum lengkap.'
    } else {
        'Momentum terbaik ' + $best.label + ' ' + (Format-Delta $best.value) + '; tekanan utama ' + $worst.label + ' ' + (Format-Delta $worst.value) + ' Rp Juta.'
    }

    Add-Rect $Slide 55 ($Top + 278) 1330 142 $colors.Soft $colors.Line 6 | Out-Null
    Add-Text $Slide 'PEMBACAAN KINERJA' 78 ($Top + 298) 310 22 12 $colors.Blue $true | Out-Null
    Add-Text $Slide (([string]$Row.label) + ' membukukan posisi Rp' + (Format-Amount $Row.current) + ' Juta. ' + $gapNarrative) 78 ($Top + 328) 1240 30 17 $colors.Ink $true | Out-Null
    Add-Text $Slide $momentumNarrative 78 ($Top + 363) 1240 24 13 $colors.Muted $false | Out-Null

    if ($null -ne $Row.achievement) {
        $progress = [Math]::Min(1, [Math]::Max(0, [double]$Row.achievement / 100))
        $progressColor = if ([double]$Row.achievement -ge 100) { $colors.Green } else { $colors.Orange }
        Add-Rect $Slide 78 ($Top + 395) 1240 10 $colors.Gray $colors.Gray 5 | Out-Null
        Add-Rect $Slide 78 ($Top + 395) (1240 * $progress) 10 $progressColor $progressColor 5 | Out-Null
    }

    Add-Text $Slide 'Warna delta: negatif merah, nol kuning, positif hijau. Seluruh angka nominal dalam Rp Juta.' 55 ($Top + 432) 980 22 11 $colors.Muted $false | Out-Null
}

function Add-ProductQualityTable($Slide, $Rows, [double]$Top = 245) {
    $headers = @('PRODUK', 'OS', 'YOY', 'YTD', 'MOM', 'MTD', 'DTD', 'SML', 'SML MTD', 'NPL', 'NPL MTD')
    $tableShape = $Slide.Shapes.AddTable($Rows.Count + 1, $headers.Count, 55, $Top, 1330, 360)
    $table = $tableShape.Table
    $widths = @(210, 125, 100, 100, 100, 100, 100, 120, 120, 120, 120)
    for ($c = 1; $c -le $headers.Count; $c++) {
        $table.Columns.Item($c).Width = $widths[$c - 1]
        Set-TableCell $table 1 $c $headers[$c - 1] $colors.Blue $colors.White $true 2 10.5
    }
    for ($r = 0; $r -lt $Rows.Count; $r++) {
        $row = $Rows[$r]
        $fill = if ($r -eq 0) { Get-Rgb '#EAF3FC' } elseif ($r % 2 -eq 0) { $colors.Soft } else { $colors.White }
        Set-TableCell $table ($r + 2) 1 ([string]$row.label) $fill $colors.Ink ($r -eq 0) 1 11
        Set-TableCell $table ($r + 2) 2 (Format-Amount $row.current) $fill $colors.Ink $true 3 11
        $keys = @('yoy', 'ytd', 'mom', 'mtd', 'dtd')
        for ($i = 0; $i -lt $keys.Count; $i++) {
            $value = $row.deltas.($keys[$i])
            $style = Get-DeltaStyle $value $false
            Set-TableCell $table ($r + 2) ($i + 3) (Format-Delta $value) $style[0] $style[1] $true 3 10
        }
        Set-TableCell $table ($r + 2) 8 (Format-Amount $row.sml.current) $fill $colors.Ink $true 3 10
        $smlStyle = Get-DeltaStyle $row.sml.deltas.mtd $true
        Set-TableCell $table ($r + 2) 9 (Format-Delta $row.sml.deltas.mtd) $smlStyle[0] $smlStyle[1] $true 3 10
        Set-TableCell $table ($r + 2) 10 (Format-Amount $row.npl.current) $fill $colors.Ink $true 3 10
        $nplStyle = Get-DeltaStyle $row.npl.deltas.mtd $true
        Set-TableCell $table ($r + 2) 11 (Format-Delta $row.npl.deltas.mtd) $nplStyle[0] $nplStyle[1] $true 3 10
    }
    Add-Text $Slide 'OS: negatif merah, nol kuning, positif hijau. SML/NPL: negatif hijau, nol kuning, positif merah.' 55 ($Top + 375) 1000 22 11 $colors.Muted $false | Out-Null
}

function Add-SeriesChart($Slide, $Timeseries, [double]$Left = 80, [double]$Top = 250, [double]$Width = 1280, [double]$Height = 410) {
    Add-Rect $Slide $Left $Top $Width $Height $colors.Soft $colors.Line 6 | Out-Null
    $labels = @($Timeseries.labels)
    $series = @($Timeseries.series)
    if ($labels.Count -lt 2 -or $series.Count -eq 0) {
        Add-Text $Slide 'Timeseries belum tersedia untuk kombinasi selector ini.' ($Left + 40) ($Top + 160) ($Width - 80) 50 20 $colors.Muted $true 2 | Out-Null
        return
    }

    $bandHeight = ($Height - 58) / $series.Count
    $palette = @($colors.Blue, $colors.Orange, $colors.Red)
    for ($s = 0; $s -lt $series.Count; $s++) {
        $item = $series[$s]
        $seriesColor = if ([string]$item.color -match '^#[0-9A-Fa-f]{6}$') { Get-Rgb ([string]$item.color) } else { $palette[$s % $palette.Count] }
        $values = @($item.values | ForEach-Object { [double]$_ })
        $minimum = ($values | Measure-Object -Minimum).Minimum
        $maximum = ($values | Measure-Object -Maximum).Maximum
        $range = [Math]::Max(1, $maximum - $minimum)
        $bandTop = $Top + 20 + ($s * $bandHeight)
        Add-Text $Slide ([string]$item.label) ($Left + 18) ($bandTop + 4) 95 22 12 $seriesColor $true | Out-Null
        $plotLeft = $Left + 125
        $plotWidth = $Width - 160
        $previousX = $null
        $previousY = $null
        for ($i = 0; $i -lt $values.Count; $i++) {
            $x = $plotLeft + (($plotWidth / [Math]::Max(1, $values.Count - 1)) * $i)
            $ratio = ($values[$i] - $minimum) / $range
            $y = $bandTop + $bandHeight - 38 - ($ratio * [Math]::Max(20, $bandHeight - 65))
            if ($null -ne $previousX) { Add-Line $Slide $previousX $previousY $x $y $seriesColor 2.6 | Out-Null }
            $dot = $Slide.Shapes.AddShape(9, $x - 4, $y - 4, 8, 8)
            $dot.Fill.Solid(); $dot.Fill.ForeColor.RGB = $seriesColor; $dot.Line.Visible = 0

            $pointText = Format-SeriesValue $item $values[$i]
            $labelWidth = 78
            $labelHeight = 17
            $labelLeft = [Math]::Max($plotLeft - 6, [Math]::Min($x - ($labelWidth / 2), $plotLeft + $plotWidth - $labelWidth + 6))
            $labelTop = $y - 23
            if ($labelTop -lt ($bandTop + 25)) { $labelTop = $y + 8 }
            Add-Rect $Slide $labelLeft $labelTop $labelWidth $labelHeight $colors.White $seriesColor 4 | Out-Null
            Add-Text $Slide $pointText ($labelLeft + 2) ($labelTop + 1) ($labelWidth - 4) ($labelHeight - 2) 7.2 $colors.Ink $true 2 | Out-Null

            $previousX = $x; $previousY = $y
        }
        Add-Text $Slide (Format-SeriesValue $item $values[-1]) ($Left + $Width - 145) ($bandTop + 4) 110 22 11 $colors.Ink $true 3 | Out-Null
        if ($s -lt $series.Count - 1) { Add-Line $Slide ($Left + 20) ($bandTop + $bandHeight) ($Left + $Width - 20) ($bandTop + $bandHeight) $colors.Line 1 | Out-Null }
    }

    $plotLeft = $Left + 125
    $plotWidth = $Width - 160
    for ($i = 0; $i -lt $labels.Count; $i++) {
        $x = $plotLeft + (($plotWidth / [Math]::Max(1, $labels.Count - 1)) * $i)
        Add-Text $Slide ([string]$labels[$i]) ($x - 35) ($Top + $Height - 28) 75 18 9.5 $colors.Muted $false 2 | Out-Null
    }
}

function Add-SeriesAnalysis($Slide, $Timeseries, [double]$Left = 80, [double]$Top = 585, [double]$Width = 1280, [double]$Height = 110) {
    $labels = @($Timeseries.labels)
    $series = @($Timeseries.series)
    if ($labels.Count -lt 2 -or $series.Count -eq 0) { return }

    Add-Rect $Slide $Left $Top $Width $Height (Get-Rgb '#F4F8FD') (Get-Rgb '#C7D9EC') 6 | Out-Null
    Add-Rect $Slide $Left $Top 7 $Height $colors.Blue $colors.Blue 0 | Out-Null
    Add-Text $Slide 'ANALISIS TREN' ($Left + 18) ($Top + 10) 155 20 10 $colors.Blue $true | Out-Null

    $contentLeft = $Left + 170
    $columnWidth = ($Width - 185) / $series.Count
    for ($s = 0; $s -lt $series.Count; $s++) {
        $item = $series[$s]
        $values = @($item.values | ForEach-Object { [double]$_ })
        if ($values.Count -eq 0) { continue }

        $startValue = $values[0]
        $latestValue = $values[-1]
        $movement = $latestValue - $startValue
        $peak = ($values | Measure-Object -Maximum).Maximum
        $peakIndex = 0
        for ($valueIndex = 0; $valueIndex -lt $values.Count; $valueIndex++) {
            if ([Math]::Abs($values[$valueIndex] - $peak) -lt 0.00001) {
                $peakIndex = $valueIndex
                break
            }
        }
        $isQuality = [string]$item.key -in @('sml', 'npl')
        $isGood = if ($isQuality) { $movement -le 0 } else { $movement -ge 0 }
        $statusColor = if ($isGood) { $colors.Green } else { $colors.Red }
        $status = if ([Math]::Abs($movement) -lt 0.00001) { 'stabil' } elseif ($isGood) { 'membaik' } else { 'perlu perhatian' }
        $columnLeft = $contentLeft + ($s * $columnWidth)

        if ($s -gt 0) { Add-Line $Slide ($columnLeft - 8) ($Top + 12) ($columnLeft - 8) ($Top + $Height - 12) $colors.Line 1 | Out-Null }
        Add-Text $Slide ([string]$item.label + ' - ' + $status) $columnLeft ($Top + 10) ($columnWidth - 14) 20 10.5 $statusColor $true | Out-Null
        Add-Text $Slide (([string]$labels[0]) + ' ' + (Format-SeriesValue $item $startValue) + '  ->  ' + ([string]$labels[-1]) + ' ' + (Format-SeriesValue $item $latestValue)) $columnLeft ($Top + 34) ($columnWidth - 14) 22 9.2 $colors.Ink $true | Out-Null
        Add-Text $Slide ('Perubahan ' + (Format-SeriesDelta $item $movement) + '; puncak ' + (Format-SeriesValue $item $peak) + ' pada ' + [string]$labels[$peakIndex] + '.') $columnLeft ($Top + 59) ($columnWidth - 14) 34 8.8 $colors.Muted $false | Out-Null
    }
}

function Add-StrategyCard($Slide, $Card, [double]$Left, [double]$Top, [double]$Width, [double]$Height) {
    Add-Rect $Slide $Left $Top $Width $Height $colors.White $colors.Line 5 | Out-Null
    Add-Rect $Slide $Left $Top 8 $Height $colors.Mentari $colors.Mentari 0 | Out-Null
    Add-Text $Slide ([string]$Card.title) ($Left + 20) ($Top + 14) ($Width - 35) 26 13 $colors.Blue $true | Out-Null
    Add-Text $Slide ([string]$Card.current_value) ($Left + 20) ($Top + 47) ($Width - 35) 38 23 $colors.Ink $true | Out-Null
    Add-Text $Slide ([string]$Card.current_label) ($Left + 20) ($Top + 84) ($Width - 35) 20 10.5 $colors.Muted $false | Out-Null
    $trend = [string]$Card.trend
    $trendValue = 0.0
    [void][double]::TryParse(($trend -replace '[^0-9,.-]', '' -replace '\.', '' -replace ',', '.'), [Globalization.NumberStyles]::Any, [Globalization.CultureInfo]::InvariantCulture, [ref]$trendValue)
    $style = Get-DeltaStyle $trendValue $false
    Add-Rect $Slide ($Left + 20) ($Top + $Height - 37) 95 24 $style[0] $style[0] 5 | Out-Null
    Add-Text $Slide $trend ($Left + 24) ($Top + $Height - 34) 87 18 10.5 $style[1] $true 2 | Out-Null
    Add-Text $Slide ([string]$Card.secondary_value) ($Left + 125) ($Top + $Height - 34) ($Width - 145) 18 10.5 $colors.Muted $true 3 | Out-Null
}

function Update-Cover($Slide) {
    for ($i = 1; $i -le $Slide.Shapes.Count; $i++) {
        $shape = $Slide.Shapes.Item($i)
        if ($shape.HasTextFrame -ne -1 -or $shape.TextFrame.HasText -ne -1) { continue }
        $text = [string]$shape.TextFrame.TextRange.Text
        if ($text -match 'Presentation\s*Title') {
            $titlePattern = [regex]'\s+-\s+'
            $coverTitle = $titlePattern.Replace(([string]$script:data.meta.title).Trim(), "`r`n", 1)
            $shape.Left = 110
            $shape.Top = 245
            $shape.Width = 760
            $shape.Height = 165
            $shape.TextFrame.TextRange.Text = $coverTitle
            $shape.TextFrame.TextRange.Font.Size = 35
            $shape.TextFrame.TextRange.Font.Bold = -1
        } elseif ($text -match 'subtitle|tagline') {
            $shape.TextFrame.TextRange.Text = $script:data.meta.subtitle
            $shape.TextFrame.TextRange.Font.Size = 22
        } elseif ($text -match '\d{1,2}\s+\w+\s+\d{4}') {
            $shape.TextFrame.TextRange.Text = $script:data.meta.period_label
        } elseif ($text -match 'Presented') {
            $shape.TextFrame.TextRange.Text = 'Presented by: BRI ' + $script:data.meta.scope_label + "`r`nRegion 13"
        }
    }
}

function Add-AgendaSlide() {
    $slide = New-ContentSlide 'Ikhtisar' 'Ikhtisar Pembahasan' 'Alur analisis disusun dari funding, kredit, kualitas, hingga strategi eksekusi.'
    $items = @($script:data.agenda)
    for ($i = 0; $i -lt $items.Count; $i++) {
        $column = $i % 2
        $row = [Math]::Floor($i / 2)
        $left = 75 + ($column * 650)
        $top = 260 + ($row * 125)
        Add-Rect $slide $left $top 600 92 $colors.Soft $colors.Line 5 | Out-Null
        Add-Rect $slide ($left + 18) ($top + 17) 58 58 $colors.Blue $colors.Blue 5 | Out-Null
        Add-Text $slide ([string]($i + 1).ToString('00')) ($left + 20) ($top + 30) 54 24 15 $colors.White $true 2 | Out-Null
        Add-Text $slide ([string]$items[$i]) ($left + 94) ($top + 28) 475 40 17 $colors.Ink $true | Out-Null
    }
}

function Add-SectionSlides($Section, [bool]$Funding = $false) {
    $overviewLabel = if ([string]$Section.scope -eq 'area6') { 'Area dan 4 Kantor Cabang' } else { [string]$Section.scope_label }
    $overview = New-ContentSlide $Section.title ($Section.title + ' - ' + $overviewLabel) ('Posisi, kontribusi, delta, RKA, dan pencapaian per ' + $script:data.meta.period_label + '.')
    $overviewRows = @($Section.overview_rows)
    if ($overviewRows.Count -eq 1 -and [string]$Section.scope -ne 'area6') {
        Add-BranchPerformanceOverview $overview $overviewRows[0] $false 245
    } else {
        Add-PerformanceTable $overview $overviewRows $false 245
    }

    $detail = New-ContentSlide $Section.title ($Section.title + ' - Per Produk') ('Scope: ' + $Section.scope_label + '. Semua angka memakai snapshot periode yang sama.')
    if ($Funding) {
        Add-PerformanceTable $detail @($Section.product_rows) $false 245
    } else {
        Add-ProductQualityTable $detail @($Section.product_rows) 245
    }

    $trend = New-ContentSlide $Section.title ($Section.title + ' - Timeseries') ('Selector ekspor: ' + $Section.scope_label + ' | ' + $Section.selected_product_label + ' | nominal per periode')
    Add-SeriesChart $trend $Section.timeseries 80 245 1280 320
    Add-SeriesAnalysis $trend $Section.timeseries 80 580 1280 112
}

function Add-ProductivitySlides() {
    $productivity = $script:data.productivity
    foreach ($category in @($productivity.categories)) {
        $slide = New-ContentSlide 'RM Productivity' ([string]$category.label) ('Scope: ' + $productivity.scope_label + ' | Posisi ' + $productivity.period_label + '.')
        $total = $category.total
        $kpis = @(
            @('Jumlah RM', ([double]$total.rm_count).ToString('N0', $culture)),
            @('Realisasi OS', [string]$total.realisasi_os_fmt),
            @('Debitur', ([double]$total.realisasi_deb).ToString('N0', $culture)),
            @('Rata-rata / RM', [string]$total.average_per_rm_fmt),
            @('Average Ticket', [string]$total.average_ticket_fmt),
            @('Rasio LAR', [string]$total.lar_pct_fmt)
        )
        for ($i = 0; $i -lt $kpis.Count; $i++) {
            $left = 55 + ($i * 220)
            Add-Rect $slide $left 230 210 82 $colors.Soft $colors.Line 5 | Out-Null
            Add-Rect $slide $left 230 210 5 $(if ($i -eq 5) { $colors.Red } else { $colors.Blue }) $(if ($i -eq 5) { $colors.Red } else { $colors.Blue }) 0 | Out-Null
            Add-Text $slide ([string]$kpis[$i][0]) ($left + 12) 246 186 18 10 $colors.Muted $true | Out-Null
            Add-Text $slide ([string]$kpis[$i][1]) ($left + 12) 268 186 30 17 $colors.Ink $true | Out-Null
        }

        $tableTop = 335
        $tableHeight = 340
        $pdwkRoles = @($category.pdwk.roles)
        if ([string]$category.key -eq 'micro' -and $pdwkRoles.Count -gt 0) {
            Add-Rect $slide 55 325 1330 72 $colors.Soft $colors.Line 5 | Out-Null
            Add-Text $slide ('REKAP PDWK PER PEMUTUS | ' + [string]$category.pdwk.total.os_fmt + ' | ' + ([double]$category.pdwk.total.deb).ToString('N0', $culture) + ' debitur') 70 332 1295 17 10 $colors.Blue $true | Out-Null

            for ($i = 0; $i -lt [Math]::Min(3, $pdwkRoles.Count); $i++) {
                $role = $pdwkRoles[$i]
                $left = 70 + ($i * 430)
                Add-Text $slide ([string]$role.label) $left 352 95 17 10 $colors.Muted $true | Out-Null
                Add-Text $slide ([string]$role.total_os_fmt + ' | ' + ([double]$role.total_deb).ToString('N0', $culture) + ' deb') ($left + 90) 350 210 20 12 $colors.Ink $true | Out-Null
                Add-Text $slide ('PDWK ' + [string]$role.pdwk_os_fmt + ' | Override ' + [string]$role.override_os_fmt + ' | Porsi ' + [string]$role.share_pct_fmt) $left 373 395 15 8 $colors.Muted $false | Out-Null
            }

            $tableTop = 410
            $tableHeight = 270
        }

        $rows = @($category.rows)
        if ($rows.Count -eq 0) {
            Add-Rect $slide 55 $tableTop 1330 $tableHeight $colors.Soft $colors.Line 5 | Out-Null
            Add-Text $slide 'Data produktivitas RM belum tersedia untuk scope dan kategori ini.' 105 ($tableTop + 115) 1230 48 20 $colors.Muted $true 2 | Out-Null
            continue
        }

        $headers = @('#', 'RM / UNIT', 'DEBITUR', 'REALISASI OS', 'AVG. TICKET', 'OS KELOLAAN', 'LAR')
        $tableShape = $slide.Shapes.AddTable($rows.Count + 1, $headers.Count, 55, $tableTop, 1330, $tableHeight)
        $table = $tableShape.Table
        $widths = @(55, 320, 120, 205, 195, 230, 205)
        for ($c = 1; $c -le $headers.Count; $c++) {
            $table.Columns.Item($c).Width = $widths[$c - 1]
            Set-TableCell $table 1 $c $headers[$c - 1] $colors.Blue $colors.White $true 2 10
        }
        for ($r = 0; $r -lt $rows.Count; $r++) {
            $row = $rows[$r]
            $fill = if ($r % 2 -eq 0) { $colors.Soft } else { $colors.White }
            Set-TableCell $table ($r + 2) 1 ([string]($r + 1)) $fill $colors.Muted $true 2 10
            Set-TableCell $table ($r + 2) 2 (([string]$row.name) + "`r`n" + ([string]$row.unit)) $fill $colors.Ink $true 1 10
            Set-TableCell $table ($r + 2) 3 ([double]$row.realisasi_deb).ToString('N0', $culture) $fill $colors.Ink $false 3 10
            Set-TableCell $table ($r + 2) 4 ([string]$row.realisasi_os_fmt) $fill $colors.Ink $true 3 10
            Set-TableCell $table ($r + 2) 5 ([string]$row.average_ticket_fmt) $fill $colors.Ink $false 3 10
            Set-TableCell $table ($r + 2) 6 ([string]$row.loan_os_fmt) $fill $colors.Ink $false 3 10
            $larStyle = Get-DeltaStyle ([double]$row.lar_pct) $true
            Set-TableCell $table ($r + 2) 7 ([string]$row.lar_pct_fmt) $larStyle[0] $larStyle[1] $true 3 10
        }
        Add-Text $slide 'Ranking berdasarkan realisasi OS. LAR dibaca sebagai indikator risiko terhadap OS kelolaan.' 55 700 1110 24 11 $colors.Muted $false | Out-Null
    }
}

function Add-IntegratedTrendSlides() {
    foreach ($group in @($script:data.trend_groups)) {
        $slide = New-ContentSlide 'Integrated Trend Lab' ([string]$group.label) (([string]$group.scope_label) + ' | ' + ([string]$group.description))
        Add-SeriesChart $slide $group 80 245 1280 430
        Add-Text $slide 'Menggunakan posisi terakhir setiap bulan agar arah perubahan antarperiode dapat dibandingkan secara konsisten.' 80 695 1180 24 11 $colors.Muted $false | Out-Null
    }
}

function Add-StrategiesSlides() {
    $cards = @($script:data.strategies)
    $scopeContext = if ([string]$script:data.meta.scope -eq 'area6') {
        'Scope Area 6 Konsolidasi.'
    } else {
        'Benchmark Area 6 sebagai pembanding ' + [string]$script:data.meta.scope_label + '.'
    }
    $landing = New-ContentSlide '8 Strategi' 'Landing 8 Strategi Dana dan Digital' ($scopeContext + ' Posisi terbaru dari report operasional yang telah masuk ke project.')
    for ($i = 0; $i -lt [Math]::Min(8, $cards.Count); $i++) {
        $col = $i % 4
        $row = [Math]::Floor($i / 4)
        Add-StrategyCard $landing $cards[$i] (55 + ($col * 332)) (245 + ($row * 230)) 305 195
    }

    $digitalCards = @($cards | Where-Object { $_.key -in @('edc', 'qris', 'qlola', 'brimo') })
    $digital = New-ContentSlide '8 Strategi' 'Optimalisasi Digital Channel' ($scopeContext + ' EDC, QRIS, QLola, dan BRImo disajikan dalam tabel ringkas dan grafik tren.')
    $tableShape = $digital.Shapes.AddTable($digitalCards.Count + 1, 5, 55, 245, 820, 360)
    $table = $tableShape.Table
    $headers = @('CHANNEL', 'POSISI', 'INDIKATOR', 'PENDUKUNG', 'TREND')
    for ($c = 1; $c -le 5; $c++) { Set-TableCell $table 1 $c $headers[$c - 1] $colors.Blue $colors.White $true 2 11 }
    for ($r = 0; $r -lt $digitalCards.Count; $r++) {
        $card = $digitalCards[$r]
        $fill = if ($r % 2 -eq 0) { $colors.Soft } else { $colors.White }
        Set-TableCell $table ($r + 2) 1 ([string]$card.title) $fill $colors.Ink $true 1 11
        Set-TableCell $table ($r + 2) 2 ([string]$card.current_value) $fill $colors.Ink $true 3 11
        Set-TableCell $table ($r + 2) 3 ([string]$card.current_label) $fill $colors.Muted $false 1 10
        Set-TableCell $table ($r + 2) 4 ([string]$card.secondary_value) $fill $colors.Ink $false 3 10
        Set-TableCell $table ($r + 2) 5 ([string]$card.trend) $fill $colors.Blue $true 3 11
    }
    Add-Text $digital 'Trend pertumbuhan' 930 245 390 28 15 $colors.Blue $true | Out-Null
    for ($i = 0; $i -lt $digitalCards.Count; $i++) {
        $trendText = [string]$digitalCards[$i].trend
        $number = 0.0
        [void][double]::TryParse(($trendText -replace '[^0-9,.-]', '' -replace '\.', '' -replace ',', '.'), [Globalization.NumberStyles]::Any, [Globalization.CultureInfo]::InvariantCulture, [ref]$number)
        $barWidth = [Math]::Min(330, [Math]::Max(4, [Math]::Abs($number) * 10))
        Add-Text $digital ([string]$digitalCards[$i].title) 930 (300 + ($i * 80)) 150 22 11 $colors.Ink $true | Out-Null
        Add-Rect $digital 1085 (302 + ($i * 80)) 250 18 $colors.Gray $colors.Gray 5 | Out-Null
        Add-Rect $digital 1085 (302 + ($i * 80)) ([Math]::Min(250, $barWidth)) 18 $(if ($number -ge 0) { $colors.Green } else { $colors.Red }) $(if ($number -ge 0) { $colors.Green } else { $colors.Red }) 5 | Out-Null
        Add-Text $digital $trendText 1085 (324 + ($i * 80)) 250 18 10 $colors.Muted $true 3 | Out-Null
    }

    $transactionCards = @($cards | Where-Object { $_.key -in @('casa', 'dormant') })
    $transaction = New-ContentSlide '8 Strategi' 'Rekening Transaksi Debitur' ($scopeContext + ' Rasio CASA debitur dan rekening dormant sebagai fokus aktivasi transaksi.')
    for ($i = 0; $i -lt $transactionCards.Count; $i++) {
        Add-StrategyCard $transaction $transactionCards[$i] (70 + ($i * 650)) 245 600 220
        $stats = @($transactionCards[$i].stats)
        for ($j = 0; $j -lt $stats.Count; $j++) {
            Add-Text $transaction ([string]$stats[$j].label) (95 + ($i * 650)) (490 + ($j * 58)) 235 24 11 $colors.Muted $false | Out-Null
            Add-Text $transaction ([string]$stats[$j].value) (335 + ($i * 650)) (488 + ($j * 58)) 300 28 15 $colors.Ink $true 3 | Out-Null
            Add-Line $transaction (95 + ($i * 650)) (520 + ($j * 58)) (635 + ($i * 650)) (520 + ($j * 58)) $colors.Line 1 | Out-Null
        }
    }

    $payroll = $cards | Where-Object { $_.key -eq 'payroll' } | Select-Object -First 1
    $closing = New-ContentSlide '8 Strategi' 'Peningkatan Payroll Berkualitas' ($scopeContext + ' Akuisisi payroll diarahkan menjadi rekening aktif, bersaldo, dan bertransaksi.')
    if ($null -ne $payroll) {
        Add-StrategyCard $closing $payroll 70 245 570 260
        $stats = @($payroll.stats)
        for ($i = 0; $i -lt $stats.Count; $i++) {
            Add-Text $closing ([string]$stats[$i].label) 95 (535 + ($i * 48)) 220 22 11 $colors.Muted $false | Out-Null
            Add-Text $closing ([string]$stats[$i].value) 320 (532 + ($i * 48)) 285 25 14 $colors.Ink $true 3 | Out-Null
        }
    }
    Add-Rect $closing 700 245 650 410 $colors.Soft $colors.Line 6 | Out-Null
    Add-Text $closing 'Fokus eksekusi' 735 280 520 35 20 $colors.Blue $true | Out-Null
    $actions = @('Konversi rekening payroll baru menjadi rekening aktif dan bertransaksi.', 'Dorong saldo mengendap melalui bundling CASA dan digital channel.', 'Pantau pertumbuhan secara kumulatif YtD dengan review cabang berkala.')
    for ($i = 0; $i -lt $actions.Count; $i++) {
        Add-Rect $closing 735 (340 + ($i * 90)) 48 48 $colors.Blue $colors.Blue 5 | Out-Null
        Add-Text $closing ([string]($i + 1)) 737 (352 + ($i * 90)) 44 22 14 $colors.White $true 2 | Out-Null
        Add-Text $closing $actions[$i] 805 (338 + ($i * 90)) 490 58 15 $colors.Ink $true | Out-Null
    }
}

try {
    if (-not (Test-Path -LiteralPath $TemplatePath)) { throw "Template tidak ditemukan: $TemplatePath" }
    if (-not (Test-Path -LiteralPath $DataPath)) { throw "Payload tidak ditemukan: $DataPath" }

    $script:data = Get-Content -LiteralPath $DataPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $ppt = New-Object -ComObject PowerPoint.Application
    $ppt.Visible = -1
    $presentation = $ppt.Presentations.Open((Resolve-Path $TemplatePath).Path, $false, $false, $false)
    $script:presentation = $presentation

    for ($i = $presentation.Slides.Count; $i -ge 2; $i--) {
        $presentation.Slides.Item($i).Delete()
    }
    Update-Cover $presentation.Slides.Item(1)
    Add-AgendaSlide
    Add-SectionSlides $data.funding $true
    Add-SectionSlides $data.sme $false
    Add-SectionSlides $data.consumer $false
    Add-ProductivitySlides
    Add-IntegratedTrendSlides
    Add-StrategiesSlides

    if (-not [IO.Path]::IsPathRooted($OutputPath)) {
        $OutputPath = Join-Path (Get-Location) $OutputPath
    }
    $outputDirectory = Split-Path -Parent $OutputPath
    if (-not (Test-Path -LiteralPath $outputDirectory)) { New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null }
    $presentation.SaveAs($OutputPath, 24)
    $count = $presentation.Slides.Count
    $presentation.Close()
    $presentation = $null
    $ppt.Quit()
    $ppt = $null

    @{ output = $OutputPath; slide_count = $count } | ConvertTo-Json -Compress
} catch {
    [Console]::Error.WriteLine($_.Exception.Message)
    [Console]::Error.WriteLine($_.ScriptStackTrace)
    exit 1
} finally {
    if ($null -ne $presentation) { try { $presentation.Close() } catch {} }
    if ($null -ne $ppt) { try { $ppt.Quit() } catch {} }
    if ($null -ne $presentation) { [void][Runtime.InteropServices.Marshal]::ReleaseComObject($presentation) }
    if ($null -ne $ppt) { [void][Runtime.InteropServices.Marshal]::ReleaseComObject($ppt) }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
