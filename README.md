# Strawpoll voter library
Simple library for PHP that adds votes in strawpoll.me polls. Pretty fast since it uses concurrent requests (default 50).

## Notes
- works as of February 13th 2020
- browser sends two additional fields (security token and authenticity token), but it appears that they're not needed for votes to count, so this library doesn't send them (omitting GET requests altogether)
- for educational purposes only, use at your own risk

## Installation
```
composer require retrowaver/strawpoll-voter
```

## Sample usage
Add votes to option with input value `12345` in poll `strawpoll.me/1234567890` using 4 proxies:

```php
use Retrowaver\Strawpoll\Strawpoll;

$strawpoll = new Strawpoll;
$successCount = $strawpoll->vote(
    '1234567890',
    '12345',
    [
        '192.168.1.1:8080',
        '192.168.1.1:8081',
        '192.168.1.1:8082',
        '192.168.1.1:8083'
    ]
);

echo $successCount; // hopefully 4!
```