<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Responder;

use App\Shared\UserInterface\Http\Responder\JsonResponder;

// @deprecated Use App\Shared\UserInterface\Http\Responder\JsonResponder instead
class_alias(
    JsonResponder::class,
    __NAMESPACE__.'\JsonResponder'
);
