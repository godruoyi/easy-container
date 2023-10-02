<?php

/*
 * This file is part of the godruoyi/easy-container.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Support;

class ThreeBody implements BookInterface
{
    public string $name = 'Three Body';

    public function name(): string
    {
        return $this->name;
    }
}
