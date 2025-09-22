<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class GenerateQrCodeUseCase
{
    public function execute(User $user): string
    {
        if (!$user->two_factor_secret) {
            throw new \InvalidArgumentException(__('auth.two_factor_not_enabled'));
        }

        if ($user->two_factor_confirmed_at) {
            throw new \InvalidArgumentException(__('auth.two_factor_already_confirmed'));
        }

        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
                new SvgImageBackEnd()
            )
        ))->writeString($user->twoFactorQrCodeUrl());

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}