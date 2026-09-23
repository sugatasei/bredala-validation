<?php

use Bredala\Validation\Messages;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

class MessagesTest extends TestCase
{
    private function messages(): Messages
    {
        return Messages::create()
            ->field('name', [
                'required' => 'Le nom est obligatoire',
                'max' => 'Le nom est trop long',
                'default' => 'Nom invalide',
            ])
            ->field('age', [
                'required' => "L'âge est obligatoire",
            ]);
    }

    public function testCreateReturnsANewInstance()
    {
        self::assertInstanceOf(Messages::class, Messages::create());
        self::assertNotSame(Messages::create(), Messages::create());
    }

    public function testFieldIsFluent()
    {
        $messages = Messages::create();

        self::assertSame($messages, $messages->field('name', []));
    }

    public function testParseMapsEachCodeToItsMessage()
    {
        self::assertSame(
            ['name' => 'Le nom est obligatoire', 'age' => "L'âge est obligatoire"],
            $this->messages()->parse(['name' => 'required', 'age' => 'required'])
        );
    }

    public function testParseFallsBackToTheFieldDefault()
    {
        self::assertSame(
            ['name' => 'Nom invalide'],
            $this->messages()->parse(['name' => 'unknown_code'])
        );
    }

    public function testParseFallsBackToTheRawCodeWithoutADefault()
    {
        // 'age' has no 'default' entry, so the untranslated code comes straight out.
        self::assertSame(['age' => 'range'], $this->messages()->parse(['age' => 'range']));
    }

    public function testParseFallsBackToTheRawCodeForAnUnconfiguredField()
    {
        self::assertSame(['city' => 'required'], $this->messages()->parse(['city' => 'required']));
    }

    public function testParseOnAnEmptyErrorListReturnsAnEmptyArray()
    {
        self::assertSame([], $this->messages()->parse([]));
    }

    public function testParsePreservesFieldOrder()
    {
        self::assertSame(
            ['age', 'name'],
            array_keys($this->messages()->parse(['age' => 'required', 'name' => 'required']))
        );
    }

    public function testRedeclaringAFieldReplacesItsWholeMap()
    {
        $messages = Messages::create()
            ->field('name', ['required' => 'first', 'max' => 'kept?'])
            ->field('name', ['required' => 'second']);

        self::assertSame(['name' => 'second'], $messages->parse(['name' => 'required']));
        self::assertSame(['name' => 'max'], $messages->parse(['name' => 'max']));
    }

    public function testParseIsUsableDirectlyWithResultErrors()
    {
        $result = Schema::structure([
            'name' => Schema::input()->required(),
        ])->validate([]);

        self::assertSame(
            ['name' => 'Le nom est obligatoire'],
            $this->messages()->parse($result->errors())
        );
    }

    public function testNestedTranslatesTheErrorsOfEachElement()
    {
        $messages = Messages::create()->nested('lines', Messages::create()->nested('*', Messages::create()
            ->field('qty', ['min' => 'Quantité trop faible'])
            ->field('sku', ['required' => 'Référence obligatoire'])));

        self::assertSame(
            ['lines' => [1 => ['sku' => 'Référence obligatoire', 'qty' => 'Quantité trop faible']]],
            $messages->parse(['lines' => [1 => ['sku' => 'required', 'qty' => 'min']]])
        );
    }

    public function testNestedAndFieldCoexistForTheSameName()
    {
        $messages = Messages::create()
            ->field('lines', ['required' => 'Au moins une ligne'])
            ->nested('lines', Messages::create()->nested('*', Messages::create()->field('qty', ['min' => 'Quantité trop faible'])));

        self::assertSame(['lines' => 'Au moins une ligne'], $messages->parse(['lines' => 'required']));
        self::assertSame(
            ['lines' => [0 => ['qty' => 'Quantité trop faible']]],
            $messages->parse(['lines' => [0 => ['qty' => 'min']]])
        );
    }

    public function testNestedErrorsWithoutMessagesKeepTheirRawCodes()
    {
        self::assertSame(
            ['lines' => [0 => ['qty' => 'min']]],
            Messages::create()->parse(['lines' => [0 => ['qty' => 'min']]])
        );
    }

    public function testNestedMessagesWorkAtAnyDepth()
    {
        $item = fn(Messages $messages) => Messages::create()->nested('*', $messages);
        $messages = Messages::create()->nested('orders', $item(Messages::create()
            ->nested('lines', $item(Messages::create()->field('qty', ['default' => 'Quantité invalide'])))));

        self::assertSame(
            ['orders' => [2 => ['lines' => [0 => ['qty' => 'Quantité invalide']]]]],
            $messages->parse(['orders' => [2 => ['lines' => [0 => ['qty' => 'min']]]]])
        );
    }

    public function testNestedMatchesASingleStructureDirectly()
    {
        $messages = Messages::create()->nested('address', Messages::create()
            ->field('city', ['required' => 'Ville obligatoire']));

        self::assertSame(
            ['address' => ['city' => 'Ville obligatoire']],
            $messages->parse(['address' => ['city' => 'required']])
        );
    }

    public function testTheWildcardFieldTranslatesTheItemsOfAScalarList()
    {
        $messages = Messages::create()->nested('tags', Messages::create()->field('*', ['max' => 'Tag trop long']));

        self::assertSame(['tags' => [0 => 'Tag trop long', 3 => 'Tag trop long']], $messages->parse(['tags' => [0 => 'max', 3 => 'max']]));
    }

    public function testAnExactFieldWinsOverTheWildcard()
    {
        $messages = Messages::create()
            ->field('*', ['required' => 'Obligatoire', 'default' => 'Invalide'])
            ->field('email', ['required' => 'Email obligatoire']);

        self::assertSame(
            ['email' => 'Email obligatoire', 'name' => 'Obligatoire', 'age' => 'Invalide'],
            $messages->parse(['email' => 'required', 'name' => 'required', 'age' => 'min'])
        );
    }

    public function testAFieldDefaultWinsOverAWildcardCode()
    {
        $messages = Messages::create()
            ->field('*', ['min' => 'Trop petit'])
            ->field('age', ['default' => 'Âge invalide']);

        self::assertSame(['age' => 'Âge invalide'], $messages->parse(['age' => 'min']));
        self::assertSame(['name' => 'Trop petit'], $messages->parse(['name' => 'min']));
    }

    public function testTheRootErrorIsUnderTheEmptyKey()
    {
        $messages = Messages::create()->field('', ['type' => 'Données invalides']);

        self::assertSame(['' => 'Données invalides'], $messages->parse(['' => 'type']));
    }

    public function testADefaultKeyCanBeUsedAsAnErrorCode()
    {
        // 'default' is a reserved-ish key: an error code literally named 'default'
        // resolves to the same entry.
        $messages = Messages::create()->field('name', ['default' => 'fallback']);

        self::assertSame(['name' => 'fallback'], $messages->parse(['name' => 'default']));
    }
}
