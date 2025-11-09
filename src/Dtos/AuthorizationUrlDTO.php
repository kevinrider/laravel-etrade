<?php

namespace KevinRider\LaravelEtrade\Dtos;

class AuthorizationUrlDTO extends BaseDTO
{
    public string $authorizationUrl;
    public string $oauthToken;
}
