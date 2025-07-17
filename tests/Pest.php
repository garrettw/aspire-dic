<?php

pest()->project()->github('outboardphp/outboard');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| want to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(Outboard\Di\Tests\TestCase::class);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

//expect()->extend('toBeOne', function () {
//    return $this->toBe(1);
//});

arch()->preset()->php();
arch()->preset()->security();

arch()->expect('*')->toBeNamespaced();
arch()->expect('*')->classes()->not->toBeAbstract();
arch()->expect('*')->classes()->not->toBeFinal();
arch()->expect('*')->classes()->not->toHavePrivateMethods();
arch()->expect('*')->toUseStrictTypes();
arch()->expect('*')->toUseStrictEquality();
arch()->expect(['sleep', 'usleep'])->not->toBeUsed();

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
