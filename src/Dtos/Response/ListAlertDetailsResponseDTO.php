<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ListAlertDetailsResponseDTO extends BaseDTO
{
    public ?int $id = null;
    public ?Carbon $createTime = null;
    public ?string $subject = null;
    public ?string $msgText = null;
    public ?Carbon $readTime = null;
    public ?Carbon $deleteTime = null;
    public ?string $symbol = null;
    public ?string $next = null;
    public ?string $prev = null;
}
