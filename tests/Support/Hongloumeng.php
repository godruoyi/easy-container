<?php

namespace Tests\Support;

class Hongloumeng implements BookInterface
{
    protected $name;

    /**
     * Get book auther.
     *
     * @return array
     */
    public function name()
    {
        return empty($this->name) ? 'hong lou meng' : $this->name;
    }

    /**
     * Reset name.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function resetName($name)
    {
        $this->name = $name;

        return $this;
    }
}
