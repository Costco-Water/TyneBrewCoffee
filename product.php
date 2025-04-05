<?php

class Product
{
    private $id;
    private $name;
    private $image;
    private $price;
    private $category;
    private $description;

    public function __construct($id, $name, $image, $price = 0, $category = '', $description = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->image = $image;
        $this->price = $price;
        $this->category = $category;
        $this->description = $description;
    }


    public function id()
    {
        return $this->id;
    }
    
    public function name()
    {
        return $this->name;
    }

    public function image()
    {
        return $this->image;
    }

    public function price()
    {
        return $this->price;
    }

    public function category()
    {
        return $this->category;
    }

    public function formattedPrice()
    {
        return number_format($this->price, 2);
    }
    
    public function description()
    {
        return $this->description;
    }
}