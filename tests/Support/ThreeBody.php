<?php

/*
 * This file is part of the godruoyi/easy-container.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Support;

class ThreeBody
{
    public $book;

    public function __construct(BookInterface $book)
    {
        $this->book = $book;
    }

    public function getName()
    {
        return $this->book->name();
    }
}
