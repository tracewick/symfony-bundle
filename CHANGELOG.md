# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries under a release heading are written by
[release-please](https://github.com/googleapis/release-please) when a release is cut; do not edit
them in a feature branch.

## [0.2.0](https://github.com/tracewick/symfony-bundle/compare/v0.1.0...v0.2.0) (2026-11-10)


### Added

* add optional controller HTTP label ([#12](https://github.com/tracewick/symfony-bundle/issues/12)) ([f165a39](https://github.com/tracewick/symfony-bundle/commit/f165a39eb283844c28fdb1ab68df586bb617dd58))
* expose process profiler diagnostics via tracewick:stats ([#16](https://github.com/tracewick/symfony-bundle/issues/16)) ([4eaaac7](https://github.com/tracewick/symfony-bundle/commit/4eaaac719c4cea019a3768d3f31a18bacbf42679))
* opt-in Messenger profiling; HTTP-only defaults ([#15](https://github.com/tracewick/symfony-bundle/issues/15)) ([66daeed](https://github.com/tracewick/symfony-bundle/commit/66daeed5cf2d2b3276e9380ca579ff5cddcf01e0))
* profile Symfony console commands ([#13](https://github.com/tracewick/symfony-bundle/issues/13)) ([55a9d74](https://github.com/tracewick/symfony-bundle/commit/55a9d74c9b8360610daafe6aa0a3479f3986226a))


### Fixed

* wire Messenger middleware before MessengerPass ([#17](https://github.com/tracewick/symfony-bundle/issues/17)) ([507c8f2](https://github.com/tracewick/symfony-bundle/commit/507c8f2ad1a7ab471eb7c26a43fc1743a37525bc))


### Chores

* release 0.2.0 ([7bf9041](https://github.com/tracewick/symfony-bundle/commit/7bf9041af18c2bac5977ab4075d3ec144b3467bf))

## 0.1.0 (2026-11-10)


### Added

* add Tracewick Symfony configuration tree ([#2](https://github.com/tracewick/symfony-bundle/issues/2)) ([3240198](https://github.com/tracewick/symfony-bundle/commit/32401987ef97991a1330f8f7a7a9a1b8cae09cf4))
* wire HTTP request profiling subscriber ([#3](https://github.com/tracewick/symfony-bundle/issues/3)) ([1ff8a08](https://github.com/tracewick/symfony-bundle/commit/1ff8a089ff867b73e6a4aedfc52988e7cdcba692))


### Chores

* release 0.1.0 ([07393c0](https://github.com/tracewick/symfony-bundle/commit/07393c0205f5893938781b36d0b4265c66782c34))

## [Unreleased]
