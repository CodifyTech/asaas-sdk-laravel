<?php

declare(strict_types=1);

use Asaas\Laravel\AsaasSdk;

if (! function_exists('asaas')) {
    /**
     * Retorna a instância do SDK Asaas (quando usado em app Laravel).
     */
    function asaas(): AsaasSdk
    {
        return app(AsaasSdk::class);
    }
}
