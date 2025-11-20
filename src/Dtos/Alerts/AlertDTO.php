<?php

namespace KevinRider\LaravelEtrade\Dtos\Alerts;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class AlertDTO extends BaseDTO
{
    public ?int $id = null;
    public ?int $createTime = null;
    public ?string $subject = null;
    public ?string $status = null;
}
