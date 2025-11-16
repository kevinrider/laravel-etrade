<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class CategoryDTO extends BaseDTO
{
    public string $categoryId;
    public string $parentId;
    public string $categoryName;
    public string $parentName;
}
