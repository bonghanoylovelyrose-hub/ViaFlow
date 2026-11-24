param(
    [Parameter(Mandatory = $true)]
    [string]$ImagePath
)

$ErrorActionPreference = 'Stop'

try {
    Add-Type -AssemblyName System.Runtime.WindowsRuntime

    $ImagePath = [System.IO.Path]::GetFullPath($ImagePath)

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

    [Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime] | Out-Null
    [Windows.Storage.Streams.IRandomAccessStream, Windows.Storage.Streams, ContentType = WindowsRuntime] | Out-Null
    [Windows.Graphics.Imaging.BitmapDecoder, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
    [Windows.Graphics.Imaging.SoftwareBitmap, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
    [Windows.Graphics.Imaging.BitmapBounds, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
    [Windows.Graphics.Imaging.BitmapTransform, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
    [Windows.Media.Ocr.OcrEngine, Windows.Media.Ocr, ContentType = WindowsRuntime] | Out-Null
    [Windows.Media.Ocr.OcrResult, Windows.Media.Ocr, ContentType = WindowsRuntime] | Out-Null

    $file = Await ([Windows.Storage.StorageFile]::GetFileFromPathAsync($ImagePath)) ([Windows.Storage.StorageFile])
    $stream = Await ($file.OpenAsync([Windows.Storage.FileAccessMode]::Read)) ([Windows.Storage.Streams.IRandomAccessStream])
    $decoder = Await ([Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($stream)) ([Windows.Graphics.Imaging.BitmapDecoder])
    $bitmap = Await ($decoder.GetSoftwareBitmapAsync()) ([Windows.Graphics.Imaging.SoftwareBitmap])
    $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()
    $result = Await ($engine.RecognizeAsync($bitmap)) ([Windows.Media.Ocr.OcrResult])
    $registerText = ''
    $registerCandidates = @()
    $registerSlotCandidates = @()

    $pixelWidth = [uint32]$decoder.PixelWidth
    $pixelHeight = [uint32]$decoder.PixelHeight

    $cropProfiles = @(
        @{ x = 0.24; y = 0.63; w = 0.48; h = 0.12; scale = 8 },
        @{ x = 0.22; y = 0.62; w = 0.52; h = 0.13; scale = 8 },
        @{ x = 0.20; y = 0.61; w = 0.56; h = 0.14; scale = 7 },
        @{ x = 0.18; y = 0.60; w = 0.60; h = 0.15; scale = 7 },
        @{ x = 0.16; y = 0.59; w = 0.64; h = 0.16; scale = 6 },
        @{ x = 0.26; y = 0.64; w = 0.44; h = 0.11; scale = 9 },
        @{ x = 0.28; y = 0.65; w = 0.40; h = 0.10; scale = 10 },
        @{ x = 0.21; y = 0.64; w = 0.50; h = 0.10; scale = 9 }
    )

    foreach ($profile in $cropProfiles) {
        $cropBounds = New-Object Windows.Graphics.Imaging.BitmapBounds
        $cropBounds.X = [uint32]([Math]::Round($pixelWidth * $profile.x))
        $cropBounds.Y = [uint32]([Math]::Round($pixelHeight * $profile.y))
        $cropBounds.Width = [uint32]([Math]::Round($pixelWidth * $profile.w))
        $cropBounds.Height = [uint32]([Math]::Round($pixelHeight * $profile.h))

        if (($cropBounds.X + $cropBounds.Width) -gt $pixelWidth) {
            $cropBounds.Width = [uint32]($pixelWidth - $cropBounds.X)
        }

        if (($cropBounds.Y + $cropBounds.Height) -gt $pixelHeight) {
            $cropBounds.Height = [uint32]($pixelHeight - $cropBounds.Y)
        }

        if ($cropBounds.Width -le 0 -or $cropBounds.Height -le 0) {
            continue
        }

        $registerTransform = New-Object Windows.Graphics.Imaging.BitmapTransform
        $registerTransform.Bounds = $cropBounds
        $registerTransform.ScaledWidth = [uint32]([Math]::Max($cropBounds.Width * $profile.scale, $cropBounds.Width))
        $registerTransform.ScaledHeight = [uint32]([Math]::Max($cropBounds.Height * $profile.scale, $cropBounds.Height))

        $registerBitmap = Await (
            $decoder.GetSoftwareBitmapAsync(
                [Windows.Graphics.Imaging.BitmapPixelFormat]::Bgra8,
                [Windows.Graphics.Imaging.BitmapAlphaMode]::Premultiplied,
                $registerTransform,
                [Windows.Graphics.Imaging.ExifOrientationMode]::IgnoreExifOrientation,
                [Windows.Graphics.Imaging.ColorManagementMode]::DoNotColorManage
            )
        ) ([Windows.Graphics.Imaging.SoftwareBitmap])

        $registerResult = Await ($engine.RecognizeAsync($registerBitmap)) ([Windows.Media.Ocr.OcrResult])
        $candidateText = ''

        if ($null -ne $registerResult -and $null -ne $registerResult.Text) {
            $candidateText = $registerResult.Text.Trim()
        }

        $slotPayload = @()

        $wholeStartX = [uint32]$cropBounds.X
        $wholeStartY = [uint32]$cropBounds.Y
        $wholeWidth = [uint32]$cropBounds.Width
        $wholeHeight = [uint32]$cropBounds.Height

        if (($wholeStartX + $wholeWidth) -gt $pixelWidth) {
            $wholeWidth = [uint32]($pixelWidth - $wholeStartX)
        }

        if (($wholeStartY + $wholeHeight) -gt $pixelHeight) {
            $wholeHeight = [uint32]($pixelHeight - $wholeStartY)
        }

        if ($wholeWidth -gt 0 -and $wholeHeight -gt 0) {
            $slotWidth = [Math]::Max([Math]::Floor($wholeWidth / 6), 1)

            for ($slotIndex = 0; $slotIndex -lt 6; $slotIndex++) {
                $slotBounds = New-Object Windows.Graphics.Imaging.BitmapBounds
                $slotBaseX = [Math]::Floor($wholeStartX + ($slotWidth * $slotIndex))
                $slotInsetX = [Math]::Max([Math]::Floor($slotWidth * 0.08), 0)
                $slotInsetY = [Math]::Max([Math]::Floor($wholeHeight * 0.10), 0)

                $slotBounds.X = [uint32]($slotBaseX + $slotInsetX)
                $slotBounds.Y = [uint32]($wholeStartY + $slotInsetY)
                if ($slotIndex -eq 5) {
                    $computedSlotWidth = [Math]::Max(($wholeStartX + $wholeWidth) - $slotBounds.X - $slotInsetX, 1)
                } else {
                    $computedSlotWidth = [Math]::Max($slotWidth - ($slotInsetX * 2), 1)
                }
                $slotBounds.Width = [uint32]$computedSlotWidth
                $slotBounds.Height = [uint32][Math]::Max($wholeHeight - ($slotInsetY * 2), 1)

                if (($slotBounds.X + $slotBounds.Width) -gt $pixelWidth) {
                    $slotBounds.Width = [uint32]($pixelWidth - $slotBounds.X)
                }

                if (($slotBounds.Y + $slotBounds.Height) -gt $pixelHeight) {
                    $slotBounds.Height = [uint32]($pixelHeight - $slotBounds.Y)
                }

                if ($slotBounds.Width -le 0 -or $slotBounds.Height -le 0) {
                    continue
                }

                $slotTransform = New-Object Windows.Graphics.Imaging.BitmapTransform
                $slotTransform.Bounds = $slotBounds
                $slotTransform.ScaledWidth = [uint32]([Math]::Max($slotBounds.Width * 10, $slotBounds.Width))
                $slotTransform.ScaledHeight = [uint32]([Math]::Max($slotBounds.Height * 10, $slotBounds.Height))

                $slotBitmap = Await (
                    $decoder.GetSoftwareBitmapAsync(
                        [Windows.Graphics.Imaging.BitmapPixelFormat]::Bgra8,
                        [Windows.Graphics.Imaging.BitmapAlphaMode]::Premultiplied,
                        $slotTransform,
                        [Windows.Graphics.Imaging.ExifOrientationMode]::IgnoreExifOrientation,
                        [Windows.Graphics.Imaging.ColorManagementMode]::DoNotColorManage
                    )
                ) ([Windows.Graphics.Imaging.SoftwareBitmap])

                $slotResult = Await ($engine.RecognizeAsync($slotBitmap)) ([Windows.Media.Ocr.OcrResult])
                $slotText = ''

                if ($null -ne $slotResult -and $null -ne $slotResult.Text) {
                    $slotText = $slotResult.Text.Trim()
                }

                $slotPayload += @{
                    index = $slotIndex
                    text = $slotText
                }
            }
        }

        $registerCandidates += @{
            text = $candidateText
            x = $cropBounds.X
            y = $cropBounds.Y
            width = $cropBounds.Width
            height = $cropBounds.Height
        }

        $registerSlotCandidates += @{
            text = $candidateText
            slots = $slotPayload
            x = $cropBounds.X
            y = $cropBounds.Y
            width = $cropBounds.Width
            height = $cropBounds.Height
        }

        if ($registerText -eq '' -and $candidateText -ne '') {
            $registerText = $candidateText
        }
    }

    $text = ''
    if ($null -ne $result -and $null -ne $result.Text) {
        $text = $result.Text.Trim()
    }

    $lines = @()

    if ($null -ne $result -and $null -ne $result.Lines) {
        foreach ($line in $result.Lines) {
            $words = @()

            foreach ($word in $line.Words) {
                $words += @{
                    text = $word.Text
                    left = [double]$word.BoundingRect.X
                    top = [double]$word.BoundingRect.Y
                    width = [double]$word.BoundingRect.Width
                    height = [double]$word.BoundingRect.Height
                }
            }

            $lines += @{
                text = $line.Text
                left = [double]$line.BoundingRect.X
                top = [double]$line.BoundingRect.Y
                width = [double]$line.BoundingRect.Width
                height = [double]$line.BoundingRect.Height
                words = $words
            }
        }
    }

    @{
        ok = $true
        text = $text
        register_text = $registerText
        register_candidates = $registerCandidates
        register_slot_candidates = $registerSlotCandidates
        lines = $lines
    } | ConvertTo-Json -Depth 6 -Compress
}
catch {
    @{
        ok = $false
        message = $_.Exception.Message
        text = ''
        register_text = ''
        register_candidates = @()
        register_slot_candidates = @()
        lines = @()
    } | ConvertTo-Json -Depth 6 -Compress
}
