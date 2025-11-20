<?php

namespace KevinRider\LaravelEtrade\Dtos;

class ListAlertDetailsResponseDTO extends BaseDTO
{
    public ?int $id = null;
    public ?int $createTime = null;
    public ?string $subject = null;
    public ?string $msgText = null;
    public ?int $readTime = null;
    public ?int $deleteTime = null;
    public ?string $symbol = null;
    public ?string $next = null;
    public ?string $prev = null;
}
