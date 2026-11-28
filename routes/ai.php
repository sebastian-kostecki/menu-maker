<?php

declare(strict_types=1);

use App\Mcp\Servers\LocalRecipeServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('menu-maker', LocalRecipeServer::class);
