<?php

declare(strict_types=1);

namespace PhpTools\Community\Captcha;

class CaptchaGenerator
{
    private int $width;
    private int $height;
    private string $fontFile;

    public function __construct(int $width = 150, int $height = 40, ?string $fontFile = null)
    {
        $this->width = $width;
        $this->height = $height;
        $this->fontFile = $fontFile ?? __DIR__ . '/fonts/arial.ttf'; // 需要提供字体文件
    }

    /**
     * Generate a math captcha: returns ['question' => '3 + 5 = ?', 'answer' => 8, 'image' => (resource)]
     */
    public function generateMath(): array
    {
        $operators = ['+', '-', '*'];
        $operator = $operators[array_rand($operators)];
        $num1 = random_int(1, 9);
        $num2 = random_int(1, 9);

        switch ($operator) {
            case '+': $answer = $num1 + $num2; break;
            case '-': $answer = $num1 - $num2; break;
            case '*': $answer = $num1 * $num2; break;
            default:  $answer = 0;
        }

        $question = "{$num1} {$operator} {$num2} = ?";
        $image = $this->createImage($question);
        return ['question' => $question, 'answer' => $answer, 'image' => $image];
    }

    /**
     * Generate a text captcha: random string.
     */
    public function generateText(int $length = 5): array
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $image = $this->createImage($code);
        return ['code' => $code, 'image' => $image];
    }

    private function createImage(string $text): \GdImage
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $this->width, $this->height, $bgColor);

        // Add noise
        for ($i = 0; $i < 50; $i++) {
            $noiseColor = imagecolorallocate($image, random_int(0, 200), random_int(0, 200), random_int(0, 200));
            imagesetpixel($image, random_int(0, $this->width), random_int(0, $this->height), $noiseColor);
        }

        // Draw text
        $textColor = imagecolorallocate($image, 0, 0, 0);
        $fontSize = (int)($this->height * 0.5);
        $x = 10;
        $y = (int)($this->height * 0.7);
        if (file_exists($this->fontFile)) {
            imagettftext($image, $fontSize, random_int(-5, 5), $x, $y, $textColor, $this->fontFile, $text);
        } else {
            imagestring($image, 5, $x, (int)($this->height * 0.2), $text, $textColor);
        }

        return $image;
    }

    /**
     * Output image directly to browser.
     */
    public function outputImage(\GdImage $image, string $format = 'png'): void
    {
        header('Content-Type: image/' . $format);
        if ($format === 'png') {
            imagepng($image);
        } elseif ($format === 'jpeg') {
            imagejpeg($image);
        } else {
            imagegif($image);
        }
        imagedestroy($image);
    }
}