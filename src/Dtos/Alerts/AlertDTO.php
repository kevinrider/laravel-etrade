<?php

namespace KevinRider\LaravelEtrade\Dtos\Alerts;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class AlertDTO extends BaseDTO
{
    public ?int $id = null;
    public ?Carbon $createTime = null;
    public ?string $subject = null;
    public ?string $status = null;
}
