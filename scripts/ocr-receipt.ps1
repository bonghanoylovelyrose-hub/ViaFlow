param(
    [Parameter(Mandatory = $true)]
    [string]$ImagePath
)

$ErrorActionPreference = 'Stop'
$PreparedImagePath = $null
$ColorImagePath = $null

try {
    Add-Type -AssemblyName System.Runtime.WindowsRuntime
    Add-Type -AssemblyName System.Drawing

    [Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime] | Out-Null
    [Windows.Storage.Streams.IRandomAccessStream, Windows.Storage.Streams, ContentType = WindowsRuntime] | Out-Null
    [Windows.Graphics.Imaging.BitmapDecoder, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
    [Windows.Graphics.Imaging.SoftwareBitmap, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
    [Windows.Media.Ocr.OcrEngine, Windows.Media.Ocr, ContentType = WindowsRuntime] | Out-Null
    [Windows.Media.Ocr.OcrResult, Windows.Media.Ocr, ContentType = WindowsRuntime] | Out-Null

    $ImagePath = [System.IO.Path]::GetFullPath($ImagePath)
    $imageDirectory = [System.IO.Path]::GetDirectoryName($ImagePath)
    $imageBaseName = [System.IO.Path]::GetFileNameWithoutExtension($ImagePath)
    $PreparedImagePath = [System.IO.Path]::Combine($imageDirectory, ($imageBaseName + '-prepared.png'))
    $ColorImagePath = [System.IO.Path]::Combine($imageDirectory, ($imageBaseName + '-color.png'))

    function Await($Operation, [Type]$ResultType) {
        $method = [System.WindowsRuntimeSystemExtensions].GetMethods() |
            Where-Object {
                $_.Name -eq 'AsTask' -and
                $_.IsGenericMethod -and
                $_.GetParameters().Count -eq 1 -and
                $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1'
            } |
            Select-Object -First 1

        $genericMethod = $method.MakeGenericMethod(@($ResultType))
        $task = $genericMethod.Invoke($null, @($Operation))

        return $task.GetAwaiter().GetResult()
    }

    function Get-TargetSize([int]$Width, [int]$Height) {
        $maxSide = [Math]::Max($Width, $Height)
        $scale = 1.0

        if ($maxSide -lt 1800) {
            $scale = 1800.0 / $maxSide
        }
        elseif ($maxSide -gt 2600) {
            $scale = 2600.0 / $maxSide
        }

        return @(
            [Math]::Max(1, [int][Math]::Round($Width * $scale)),
            [Math]::Max(1, [int][Math]::Round($Height * $scale))
        )
    }

    function Save-ResizedColorImage([System.Drawing.Image]$Source, [string]$TargetPath) {
        $targetSize = Get-TargetSize $Source.Width $Source.Height
        $targetWidth = $targetSize[0]
        $targetHeight = $targetSize[1]
        $bitmap = New-Object System.Drawing.Bitmap $targetWidth, $targetHeight, ([System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)

        try {
            $graphics.CompositingMode = [System.Drawing.Drawing2D.CompositingMode]::SourceCopy
            $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
            $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
            $graphics.Clear([System.Drawing.Color]::White)
            $graphics.DrawImage($Source, 0, 0, $targetWidth, $targetHeight)
            $bitmap.Save($TargetPath, [System.Drawing.Imaging.ImageFormat]::Png)
        }
        finally {
            $graphics.Dispose()
            $bitmap.Dispose()
        }
    }

    function Save-GrayscaleContrastImage([System.Drawing.Image]$Source, [string]$TargetPath) {
        $targetSize = Get-TargetSize $Source.Width $Source.Height
        $targetWidth = $targetSize[0]
        $targetHeight = $targetSize[1]
        $contrast = 1.45
        $redWeight = 0.299 * $contrast
        $greenWeight = 0.587 * $contrast
        $blueWeight = 0.114 * $contrast
        $offset = 0.5 - (0.5 * $contrast)

        $matrix = New-Object System.Drawing.Imaging.ColorMatrix
        $matrix.Matrix00 = $redWeight
        $matrix.Matrix01 = $redWeight
        $matrix.Matrix02 = $redWeight
        $matrix.Matrix03 = 0
        $matrix.Matrix04 = 0
        $matrix.Matrix10 = $greenWeight
        $matrix.Matrix11 = $greenWeight
        $matrix.Matrix12 = $greenWeight
        $matrix.Matrix13 = 0
        $matrix.Matrix14 = 0
        $matrix.Matrix20 = $blueWeight
        $matrix.Matrix21 = $blueWeight
        $matrix.Matrix22 = $blueWeight
        $matrix.Matrix23 = 0
        $matrix.Matrix24 = 0
        $matrix.Matrix30 = 0
        $matrix.Matrix31 = 0
        $matrix.Matrix32 = 0
        $matrix.Matrix33 = 1
        $matrix.Matrix34 = 0
        $matrix.Matrix40 = $offset
        $matrix.Matrix41 = $offset
        $matrix.Matrix42 = $offset
        $matrix.Matrix43 = 0
        $matrix.Matrix44 = 1

        $attributes = New-Object System.Drawing.Imaging.ImageAttributes
        $attributes.SetColorMatrix($matrix)

        $bitmap = New-Object System.Drawing.Bitmap $targetWidth, $targetHeight, ([System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        $destination = New-Object System.Drawing.Rectangle 0, 0, $targetWidth, $targetHeight

        try {
            $graphics.CompositingMode = [System.Drawing.Drawing2D.CompositingMode]::SourceCopy
            $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
            $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
            $graphics.Clear([System.Drawing.Color]::White)
            $graphics.DrawImage(
                $Source,
                $destination,
                0,
                0,
                $Source.Width,
                $Source.Height,
                [System.Drawing.GraphicsUnit]::Pixel,
                $attributes
            )
            $bitmap.Save($TargetPath, [System.Drawing.Imaging.ImageFormat]::Png)
        }
        finally {
            $graphics.Dispose()
            $bitmap.Dispose()
            $attributes.Dispose()
        }
    }

    function Prepare-ReceiptImages([string]$SourcePath, [string]$ColorTarget, [string]$PreparedTarget) {
        $source = [System.Drawing.Image]::FromFile($SourcePath)
        $upright = $null

        try {
            try {
                $orientationPropertyId = 274

                if ($source.PropertyIdList -contains $orientationPropertyId) {
                    $orientation = [BitConverter]::ToUInt16($source.GetPropertyItem($orientationPropertyId).Value, 0)

                    switch ($orientation) {
                        2 { $source.RotateFlip([System.Drawing.RotateFlipType]::RotateNoneFlipX) }
                        3 { $source.RotateFlip([System.Drawing.RotateFlipType]::Rotate180FlipNone) }
                        4 { $source.RotateFlip([System.Drawing.RotateFlipType]::RotateNoneFlipY) }
                        5 { $source.RotateFlip([System.Drawing.RotateFlipType]::Rotate90FlipX) }
                        6 { $source.RotateFlip([System.Drawing.RotateFlipType]::Rotate90FlipNone) }
                        7 { $source.RotateFlip([System.Drawing.RotateFlipType]::Rotate270FlipX) }
                        8 { $source.RotateFlip([System.Drawing.RotateFlipType]::Rotate270FlipNone) }
                    }

                    $source.RemovePropertyItem($orientationPropertyId)
                }
            }
            catch {
                # EXIF orientation is best-effort; continue with the decoded bitmap.
            }

            $upright = New-Object System.Drawing.Bitmap $source.Width, $source.Height, ([System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
            $graphics = [System.Drawing.Graphics]::FromImage($upright)

            try {
                $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
                $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
                $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
                $graphics.Clear([System.Drawing.Color]::White)
                $graphics.DrawImage($source, 0, 0, $source.Width, $source.Height)
            }
            finally {
                $graphics.Dispose()
            }

            Save-ResizedColorImage $upright $ColorTarget
            Save-GrayscaleContrastImage $upright $PreparedTarget
        }
        finally {
            if ($null -ne $upright) {
                $upright.Dispose()
            }

            $source.Dispose()
        }
    }

    function Get-OcrResultText($Result) {
        if ($null -eq $Result) {
            return ''
        }

        $lines = New-Object System.Collections.Generic.List[string]

        if ($null -ne $Result.Lines) {
            foreach ($line in $Result.Lines) {
                if ($null -eq $line) {
                    continue
                }

                $lineText = ([string]$line.Text).Trim()

                if ($lineText -ne '') {
                    $lines.Add($lineText)
                }
            }
        }

        if ($lines.Count -gt 0) {
            return ($lines -join "`n")
        }

        if ($null -ne $Result.Text) {
            return ([string]$Result.Text).Trim()
        }

        return ''
    }

    function Read-ReceiptText([string]$Path) {
        if (-not [System.IO.File]::Exists($Path)) {
            throw "Prepared OCR image was not created: $Path"
        }

        $file = Await ([Windows.Storage.StorageFile]::GetFileFromPathAsync($Path)) ([Windows.Storage.StorageFile])
        $stream = $null
        $bitmap = $null

        try {
            $stream = Await ($file.OpenAsync([Windows.Storage.FileAccessMode]::Read)) ([Windows.Storage.Streams.IRandomAccessStream])
            $decoder = Await ([Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($stream)) ([Windows.Graphics.Imaging.BitmapDecoder])
            $bitmap = Await ($decoder.GetSoftwareBitmapAsync()) ([Windows.Graphics.Imaging.SoftwareBitmap])
            $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()

            if ($null -eq $engine) {
                throw 'Windows OCR is not available for the current user profile.'
            }

            $result = Await ($engine.RecognizeAsync($bitmap)) ([Windows.Media.Ocr.OcrResult])

            return Get-OcrResultText $result
        }
        finally {
            if ($null -ne $bitmap) {
                $bitmap.Dispose()
            }

            if ($null -ne $stream) {
                $stream.Dispose()
            }
        }
    }

    function Get-OcrScore([string]$Text) {
        if ([string]::IsNullOrWhiteSpace($Text)) {
            return 0
        }

        $score = 0

        if ($Text -match '(?i)\bref(?:erence)?\.?\s*(?:no|noa|number|num)\b') {
            $score += 40
        }

        if ($Text -match '(?i)\b(?:total\s+amount\s+sent|amount\s+(?:paid|sent)|payment\s+amount|amount)\b') {
            $score += 40
        }

        if ($Text -match '(?i)\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4}\b') {
            $score += 40
        }

        if ($Text -match '(?i)\b\d{1,2}:\d{2}\s*(?:am|pm)\b') {
            $score += 10
        }

        if ($Text -match '(?i)\b(?:gcash|maya|bpi|bdo|metrobank|unionbank)\b') {
            $score += 10
        }

        $digitCount = ($Text -replace '[^0-9]', '').Length
        $score += [Math]::Min(20, [int]($digitCount / 8))

        return $score
    }

    function Merge-ReceiptText([string]$Primary, [string]$Secondary) {
        $lines = New-Object System.Collections.Generic.List[string]
        $seen = @{}

        foreach ($text in @($Primary, $Secondary)) {
            if ([string]::IsNullOrWhiteSpace($text)) {
                continue
            }

            foreach ($line in ($text -split "`n")) {
                $line = $line.Trim()

                if ($line -eq '') {
                    continue
                }

                $key = $line.ToLowerInvariant()

                if ($seen.ContainsKey($key)) {
                    continue
                }

                $seen[$key] = $true
                $lines.Add($line)
            }
        }

        return ($lines -join "`n")
    }

    Prepare-ReceiptImages $ImagePath $ColorImagePath $PreparedImagePath

    $colorText = ''
    $preparedText = ''
    $errors = New-Object System.Collections.Generic.List[string]

    try {
        $colorText = Read-ReceiptText $ColorImagePath
    }
    catch {
        $errors.Add($_.Exception.Message)
    }

    try {
        $preparedText = Read-ReceiptText $PreparedImagePath
    }
    catch {
        $errors.Add($_.Exception.Message)
    }

    if ([string]::IsNullOrWhiteSpace($colorText) -and [string]::IsNullOrWhiteSpace($preparedText)) {
        if ($errors.Count -gt 0) {
            throw ($errors -join ' ')
        }

        throw 'No readable text was found in the uploaded receipt.'
    }

    $colorScore = Get-OcrScore $colorText
    $preparedScore = Get-OcrScore $preparedText

    if ($preparedScore -gt $colorScore) {
        $primaryText = $preparedText
        $secondaryText = $colorText
    }
    else {
        $primaryText = $colorText
        $secondaryText = $preparedText
    }

    $text = Merge-ReceiptText $primaryText $secondaryText

    @{
        ok = $true
        text = $text
        image_path = $PreparedImagePath
        color_text = $colorText
        prepared_text = $preparedText
    } | ConvertTo-Json -Compress
}
catch {
    @{
        ok = $false
        message = $_.Exception.Message
        text = ''
    } | ConvertTo-Json -Compress
}
finally {
    if ($ColorImagePath -and [System.IO.File]::Exists($ColorImagePath)) {
        [System.IO.File]::Delete($ColorImagePath)
    }

    if ($PreparedImagePath -and [System.IO.File]::Exists($PreparedImagePath)) {
        [System.IO.File]::Delete($PreparedImagePath)
    }
}
