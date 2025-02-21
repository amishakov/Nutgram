<?php

use SergiX44\Nutgram\Cache\Adapters\ArrayCache;
use SergiX44\Nutgram\Middleware\RateLimit;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Support\RateLimiter;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;

beforeEach(function () {
    RateLimit::$warningCallback = null;

    $this->bot = Nutgram::fake();

    $this->bot->setCommonUser(User::make(
        id: 123456789,
        is_bot: false,
        first_name: 'Tony',
        last_name: 'Stark',
        username: 'IronMan',
        language_code: 'en',
    ));

    $this->bot->setCommonChat(Chat::make(
        id: 123456789,
        type: ChatType::PRIVATE,
        username: 'IronMan',
        first_name: 'Tony',
        last_name: 'Stark',
    ));
});

it('throttles a handler', function () {
    $this->bot->onText('hi', function (Nutgram $bot) {
        $bot->sendMessage('Hello!');
    })->throttle(2);

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));

    $this->bot->hearText('hi')->reply()->assertReplyText('Hello!');
    $this->bot->hearText('hi')->reply()->assertReplyText('Hello!');
    $this->bot->hearText('hi')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');
    $this->bot->hearText('hi')->reply()->assertNoReply();

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:02:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:02:00'));

    $this->bot->hearText('hi')->reply()->assertReplyText('Hello!');
});

it('throttles with a custom warning message', function () {
    RateLimit::$warningCallback = function (Nutgram $bot, int $availableIn) {
        $bot->sendMessage("You're sending too many messages. Please wait $availableIn seconds.");
    };

    $this->bot->onText('hi', function (Nutgram $bot) {
        $bot->sendMessage('Hello!');
    })->throttle(2);

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));

    $this->bot->hearText('hi')->reply()->assertReplyText('Hello!');
    $this->bot->hearText('hi')->reply()->assertReplyText('Hello!');

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:00:10'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:00:10'));

    $this->bot->hearText('hi')->reply()->assertReplyText("You're sending too many messages. Please wait 50 seconds.");
});

it('throttles a group', function () {
    $this->bot->group(function (Nutgram $bot) {
        $bot->onText('hello', function (Nutgram $bot) {
            $bot->sendMessage('world');
        });

        $bot->onText('foo', function (Nutgram $bot) {
            $bot->sendMessage('bar');
        });
    })->throttle(2);

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));

    $this->bot->hearText('hello')->reply()->assertReplyText('world');
    $this->bot->hearText('foo')->reply()->assertReplyText('bar');
    $this->bot->hearText('hello')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:02:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:02:00'));
});

it('throttles globally', function () {
    $this->bot->throttle(2);

    $this->bot->onText('hello', function (Nutgram $bot) {
        $bot->sendMessage('world');
    });

    $this->bot->onText('foo', function (Nutgram $bot) {
        $bot->sendMessage('bar');
    });

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));

    $this->bot->hearText('hello')->reply()->assertReplyText('world');
    $this->bot->hearText('foo')->reply()->assertReplyText('bar');
    $this->bot->hearText('foo')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:02:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:02:00'));
});

it('throttles hard', function () {
    $this->bot->throttle(4);

    $this->bot->onText('start', function (Nutgram $bot) {
        $bot->sendMessage('yep');
    });

    $this->bot->group(function (Nutgram $bot) {
        $bot->onText('hello', function (Nutgram $bot) {
            $bot->sendMessage('world');
        })->throttle(2);

        $bot->onText('foo', function (Nutgram $bot) {
            $bot->sendMessage('bar');
        })->throttle(1);

        $bot->onText('group', function (Nutgram $bot) {
            $bot->sendMessage('nope');
        });

        $bot->onText('illogic', function (Nutgram $bot) {
            $bot->sendMessage('crazy');
        })->throttle(10);
    })->throttle(3);

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-01 00:00:00'));

    $this->bot->hearText('start')->reply()->assertReplyText('yep');
    $this->bot->hearText('start')->reply()->assertReplyText('yep');
    $this->bot->hearText('start')->reply()->assertReplyText('yep');
    $this->bot->hearText('start')->reply()->assertReplyText('yep');
    $this->bot->hearText('start')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-02 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-02 00:00:00'));

    $this->bot->hearText('group')->reply()->assertReplyText('nope');
    $this->bot->hearText('group')->reply()->assertReplyText('nope');
    $this->bot->hearText('group')->reply()->assertReplyText('nope');
    $this->bot->hearText('group')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-03 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-03 00:00:00'));

    $this->bot->hearText('illogic')->reply()->assertReplyText('crazy');
    $this->bot->hearText('illogic')->reply()->assertReplyText('crazy');
    $this->bot->hearText('illogic')->reply()->assertReplyText('crazy');
    $this->bot->hearText('illogic')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');

    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-04 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-04 00:00:00'));

    $this->bot->hearText('hello')->reply()->assertReplyText('world');
    $this->bot->hearText('hello')->reply()->assertReplyText('world');
    $this->bot->hearText('hello')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');


    ArrayCache::setTestNow(new DateTimeImmutable('2025-01-05 00:00:00'));
    RateLimiter::setTestNow(new DateTimeImmutable('2025-01-05 00:00:00'));

    $this->bot->hearText('foo')->reply()->assertReplyText('bar');
    $this->bot->hearText('foo')->reply()->assertReplyText('Too many messages, please wait a bit. This message will only be sent once until the rate limit is reset.');
});
