<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class CategoryDTO extends BaseDTO
{
    public ?string $categoryId = null;
    public ?string $parentId = null;
    public ?string $categoryName = null;
    public ?string $parentName = null;
}
