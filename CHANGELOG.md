Phpbrake Changelog
==================

### [v0.8.0][v0.8.0] (Apr 5, 2021)

* Added Remote Config feature to fetch project error configuration settings
  from the remote once every 10 minutes.
* Documented the `remoteConfig` option to enable/disable, enabled by default.
  ([#114](https://github.com/airbrake/phpbrake/pull/114))

### [v0.7.5][v0.7.5] (Nov 4, 2020)

* Changed deprecated `keysBlacklist` in favor of the new option `keysBlocklist`
  ([#104](https://github.com/airbrake/phpbrake/pull/104))


### [v0.7.4][v0.7.4] (October 15, 2020)

* Changed `guzzlehttp/guzzle` dependency version requirement from `^6.3` to
  `>=6.3` ([#109](https://github.com/airbrake/phpbrake/pull/109))

### [v0.4.0][v0.4.0] (Sep 7, 2017)

* SendNotice returns notice with id and error keys set.
* Guzzle is now the only built-in HTTP client.

### [v0.2.4][v0.2.4] (May 11, 2017)

* Started sending customizable severity option (defaults to `error`)
  ([#55](https://github.com/airbrake/phpbrake/pull/55))

### [v0.2.0][v0.2.0] (July 21, 2016)

* Introduced new option: `httpClient`
  ([#38](https://github.com/airbrake/phpbrake/pull/38))

[v0.2.0]: https://github.com/airbrake/phpbrake/releases/tag/v0.2.0
[v0.2.4]: https://github.com/airbrake/phpbrake/releases/tag/v0.2.4
[v0.4.0]: https://github.com/airbrake/phpbrake/releases/tag/v0.4.0
[v0.7.4]: https://github.com/airbrake/phpbrake/releases/tag/v0.7.4
[v0.7.5]: https://github.com/airbrake/phpbrake/releases/tag/v0.7.5
[v0.8.0]: https://github.com/airbrake/phpbrake/releases/tag/v0.8.0
