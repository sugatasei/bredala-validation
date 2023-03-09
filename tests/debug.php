<?php

use Bredala\Validation\Fields\ArrayField;
use Bredala\Validation\Fields\IntegerField;
use Bredala\Validation\Fields\StringField;
use Bredala\Validation\Form;

include __DIR__ . '/bootstrap.php';

class ChildForm extends Form
{
    public function __construct()
    {
        $this->input('name', [$this, 'name']);
        $this->integer('age', [$this, 'age']);
    }

    public function name(?string $value)
    {
        StringField::required($value);
    }

    public function age(?int $value)
    {
        IntegerField::required($value);
    }
}

class MainForm extends Form
{
    /**
     * @var ChildForm[]
     */
    public array $users = [];

    public function __construct()
    {
        $this->input('title', [$this, 'title']);
        $this->mapInteger('categories', [$this, 'cat']);
        $this->mapArray('users', [$this, 'users']);
    }

    public function title(?string $value)
    {
        StringField::required($value);
    }

    public function cat(?array $values)
    {
        ArrayField::skip($values);
        ArrayField::max($values, 3);
    }

    public function users(?array $values)
    {
        ArrayField::required($values);
        ArrayField::nested($values, $this, new ChildForm, $this->users);
        ArrayField::max($values, 3);
    }
}

$main = new MainForm;

$main->validate([
    'title' => 'Pullover',
    'categories' => ['1', 2, 3, '5'],
    'users' => [
        'tom' => ['name' => 'Tom', 'age' => 3],
        'tim' => ['name' => 'Tim', 'age' => 'onon'],
    ]
]);

print_r($main->values());
print_r($main->errors());

foreach ($main->users as $k => $user) {
    var_dump($k);
    print_r($user->errors());
}
