# Changelog

## [1.0.1](https://github.com/GaiaTools/fulcrum-settings/compare/v1.0.0...v1.0.1) (2026-06-06)


### Bug Fixes

* prevent double-encoding of array/json values in set() ([b302f19](https://github.com/GaiaTools/fulcrum-settings/commit/b302f19d2b5ca7f8d697bf3502efbb73cca12bfc))

## 1.0.0 (2026-06-05)


### Features

* add serialization, collection proxy, and load helpers to settings classes ([#9](https://github.com/GaiaTools/fulcrum-settings/issues/9)) ([ebd4ea4](https://github.com/GaiaTools/fulcrum-settings/commit/ebd4ea4577d4118e8df10caa722412408cfdb60e))
* add support for groups ([#11](https://github.com/GaiaTools/fulcrum-settings/issues/11)) ([487b6c0](https://github.com/GaiaTools/fulcrum-settings/commit/487b6c01f8e1c5d3b3c3c84e07e3b98c5dd26da2))
* cleanup class methods ([#16](https://github.com/GaiaTools/fulcrum-settings/issues/16)) ([78cdbf9](https://github.com/GaiaTools/fulcrum-settings/commit/78cdbf9d151b13e497916c2068c9c3d316ad0649))
* cleanup migrations ([#17](https://github.com/GaiaTools/fulcrum-settings/issues/17)) ([4975ae3](https://github.com/GaiaTools/fulcrum-settings/commit/4975ae35d72ee24d98f087ae44df408011aa86fb))
* improve GitHub actions ([#14](https://github.com/GaiaTools/fulcrum-settings/issues/14)) ([e8180d8](https://github.com/GaiaTools/fulcrum-settings/commit/e8180d8511aa05039ba9df085a78343136191073))
* initial fulcrum settings ([#1](https://github.com/GaiaTools/fulcrum-settings/issues/1)) ([7dc1101](https://github.com/GaiaTools/fulcrum-settings/commit/7dc11016071955dc35e6f58482cba2fd50c3d241))


### Bug Fixes

* add value type to getGroupKeys [@method](https://github.com/method) for PHPStan L9 ([e80eb6f](https://github.com/GaiaTools/fulcrum-settings/commit/e80eb6f4b115e51dfead48886543760c19e66f87))
* harden rule evaluation, import dry-run, and bucket distribution ([67140fa](https://github.com/GaiaTools/fulcrum-settings/commit/67140fae7d6a12db262c77112fb1e31dc72af5b0))
* remove invalid `after` modifier from `group` column in settings table migration ([#32](https://github.com/GaiaTools/fulcrum-settings/issues/32)) ([20d4ced](https://github.com/GaiaTools/fulcrum-settings/commit/20d4cedc1e28e1014ecf8f9065533f14612f89fe))
* resolve SonarQube violations in SettingRule and RuleEvaluator ([6c4c803](https://github.com/GaiaTools/fulcrum-settings/commit/6c4c803fcc3d0bb8ccfabcebba0077ccb6ad8faa))


### Miscellaneous Chores

* add .gitignore ([082426b](https://github.com/GaiaTools/fulcrum-settings/commit/082426bdb60492f5cbcb1871ebcb62e82681a0de))
* apply pint code style fixes across src and tests ([#40](https://github.com/GaiaTools/fulcrum-settings/issues/40)) ([bec311c](https://github.com/GaiaTools/fulcrum-settings/commit/bec311c04ac79c83caf1327e1b7d08ed360de6e9))
* **ci:** update SonarQube host URL ([#39](https://github.com/GaiaTools/fulcrum-settings/issues/39)) ([186b5b1](https://github.com/GaiaTools/fulcrum-settings/commit/186b5b16fea6d1c329bb2c32a19e7f334f8ef3e7))
* configure release-please for automated releases ([#23](https://github.com/GaiaTools/fulcrum-settings/issues/23)) ([6b02ac3](https://github.com/GaiaTools/fulcrum-settings/commit/6b02ac362a0a56eee0442ff3331a46aa872560c5))
* **deps:** bump actions/download-artifact from 7 to 8 ([#28](https://github.com/GaiaTools/fulcrum-settings/issues/28)) ([9806dde](https://github.com/GaiaTools/fulcrum-settings/commit/9806ddeeab724fc7e2544f246d3411014aaf66e3))
* **deps:** bump actions/upload-artifact from 6 to 7 ([#27](https://github.com/GaiaTools/fulcrum-settings/issues/27)) ([9f39566](https://github.com/GaiaTools/fulcrum-settings/commit/9f39566501244cf790a618d73940a1b9fd25fda4))
* **deps:** bump azuyalabs/yasumi from 2.10.0 to 2.11.0 ([c3fef4d](https://github.com/GaiaTools/fulcrum-settings/commit/c3fef4d5910c79fbe8f93ec995eb199a012a9a9f))
* **deps:** bump dependabot/fetch-metadata from 2 to 3 ([9b84970](https://github.com/GaiaTools/fulcrum-settings/commit/9b849708c97d3216cf699073c85d383ececfad78))
* **deps:** bump larastan/larastan from 3.9.2 to 3.9.3 ([#29](https://github.com/GaiaTools/fulcrum-settings/issues/29)) ([2751e67](https://github.com/GaiaTools/fulcrum-settings/commit/2751e674e476fbaed63db8c812aa64dd749c36f7))
* **deps:** bump larastan/larastan from 3.9.3 to 3.9.6 ([d1d10ca](https://github.com/GaiaTools/fulcrum-settings/commit/d1d10ca4f402d2379bca47cf8ab5b0b94380056e))
* **deps:** bump larastan/larastan from 3.9.6 to 3.10.0 ([03e2c2d](https://github.com/GaiaTools/fulcrum-settings/commit/03e2c2d22254a80ad97ed6f242781f004cdac7bf))
* **deps:** bump laravel/pennant from 1.19.0 to 1.20.0 ([#21](https://github.com/GaiaTools/fulcrum-settings/issues/21)) ([c555d6d](https://github.com/GaiaTools/fulcrum-settings/commit/c555d6d83d09a883b380682327fdddb436be8465))
* **deps:** bump laravel/pennant from 1.20.0 to 1.22.0 ([2085de3](https://github.com/GaiaTools/fulcrum-settings/commit/2085de34cd0ab1a175f154e3943e03d93c308e77))
* **deps:** bump laravel/pennant from 1.22.0 to 1.23.0 ([97d105f](https://github.com/GaiaTools/fulcrum-settings/commit/97d105fb2194aaa9b5d6838644eae5daee3df45c))
* **deps:** bump laravel/pint from 1.27.1 to 1.29.0 ([a82969b](https://github.com/GaiaTools/fulcrum-settings/commit/a82969b690133f26ab8edf463e8c541477222ec0))
* **deps:** bump laravel/pint from 1.29.0 to 1.29.1 ([4f7b136](https://github.com/GaiaTools/fulcrum-settings/commit/4f7b136d565c314121b21a88660e11e28cdcb106))
* **deps:** bump laravel/telescope from 5.17.0 to 5.18.0 ([#22](https://github.com/GaiaTools/fulcrum-settings/issues/22)) ([88cbd32](https://github.com/GaiaTools/fulcrum-settings/commit/88cbd327c6c3bcc837aedb2a01641f138254a1b8))
* **deps:** bump laravel/telescope from 5.18.0 to 5.19.0 ([1ff4fb6](https://github.com/GaiaTools/fulcrum-settings/commit/1ff4fb61557bd32367b98440fdde8259f1a1a247))
* **deps:** bump laravel/telescope from 5.19.0 to 5.20.0 ([0c3d22d](https://github.com/GaiaTools/fulcrum-settings/commit/0c3d22db11e3ba393dbe8501f0752d5fd98eb04d))
* **deps:** bump league/commonmark from 2.8.0 to 2.8.1 ([#31](https://github.com/GaiaTools/fulcrum-settings/issues/31)) ([df058cc](https://github.com/GaiaTools/fulcrum-settings/commit/df058cc46de4366ff9bc362b441ea2b3be664a2b))
* **deps:** bump pestphp/pest from 4.3.2 to 4.4.1 ([#19](https://github.com/GaiaTools/fulcrum-settings/issues/19)) ([c7f1bd8](https://github.com/GaiaTools/fulcrum-settings/commit/c7f1bd82862deda21d3fc8e2dfada277fbcbc8c8))
* **deps:** bump pestphp/pest from 4.4.1 to 4.4.3 ([c95d0c1](https://github.com/GaiaTools/fulcrum-settings/commit/c95d0c1a71a86bbc5b511b18804cffe4c1d25f9f))
* **deps:** bump pestphp/pest from 4.4.3 to 4.4.6 ([eea74c3](https://github.com/GaiaTools/fulcrum-settings/commit/eea74c31fa584693935f612de55a05300d1d0880))
* **deps:** bump pestphp/pest from 4.4.6 to 4.6.1 ([3aa295c](https://github.com/GaiaTools/fulcrum-settings/commit/3aa295cf08d1780d2db622be93a535fb477b3031))
* **deps:** bump pestphp/pest from 4.6.1 to 4.6.3 ([6142212](https://github.com/GaiaTools/fulcrum-settings/commit/6142212344b87dd09b48b529985a2829aa9ab7e4))
* **deps:** bump pestphp/pest from 4.6.3 to 4.7.0 ([a333cff](https://github.com/GaiaTools/fulcrum-settings/commit/a333cff8e81f417d3372f06b7a5cc1a82d83ab1a))
* **deps:** bump pestphp/pest from 4.7.0 to 4.7.2 ([043a6cf](https://github.com/GaiaTools/fulcrum-settings/commit/043a6cf69345832956c5d956c6fb426a2bf3dca7))
* **deps:** bump pestphp/pest-plugin-laravel from 4.0.0 to 4.1.0 ([#30](https://github.com/GaiaTools/fulcrum-settings/issues/30)) ([6e488ff](https://github.com/GaiaTools/fulcrum-settings/commit/6e488ff699226cd70495bdf92f28c23d31b33950))
* **deps:** bump phpstan/phpstan from 2.1.39 to 2.1.40 ([#20](https://github.com/GaiaTools/fulcrum-settings/issues/20)) ([fff9441](https://github.com/GaiaTools/fulcrum-settings/commit/fff94414c5e8d330d8e99fd99b7696f9030e721b))
* **deps:** bump phpstan/phpstan from 2.1.40 to 2.1.44 ([7df81d4](https://github.com/GaiaTools/fulcrum-settings/commit/7df81d4afc45bb648b920102a93ecf5101e51453))
* **deps:** bump phpstan/phpstan from 2.1.44 to 2.1.46 ([92813a3](https://github.com/GaiaTools/fulcrum-settings/commit/92813a3f60b26cccbe34de889f97d2b8f51fbe23))
* **deps:** bump phpstan/phpstan from 2.1.46 to 2.1.49 ([5176d0a](https://github.com/GaiaTools/fulcrum-settings/commit/5176d0aa27fd8e36aec0c147c274781aa940abfd))
* **deps:** bump phpstan/phpstan from 2.1.49 to 2.1.51 ([7653a38](https://github.com/GaiaTools/fulcrum-settings/commit/7653a380c5bda550e369c81fe982947bb7b479ad))
* **deps:** bump phpstan/phpstan from 2.1.51 to 2.1.54 ([e3ed758](https://github.com/GaiaTools/fulcrum-settings/commit/e3ed758f1989836b51cc9bed080860878961bbf0))
* **deps:** bump phpstan/phpstan from 2.1.54 to 2.1.55 ([6a9da1e](https://github.com/GaiaTools/fulcrum-settings/commit/6a9da1e83ea775e227f5ca8eba8bd1904aa6dfe5))
* **deps:** bump phpstan/phpstan from 2.1.55 to 2.2.1 ([96e6e39](https://github.com/GaiaTools/fulcrum-settings/commit/96e6e390889ff54dc808b604d4e75160c6efc581))
* **deps:** bump phpstan/phpstan from 2.2.1 to 2.2.2 ([6697ab7](https://github.com/GaiaTools/fulcrum-settings/commit/6697ab7872044d8e7702c6997b638c3567dfa987))
* **deps:** bump psy/psysh from 0.12.18 to 0.12.20 ([#25](https://github.com/GaiaTools/fulcrum-settings/issues/25)) ([ebf7a0e](https://github.com/GaiaTools/fulcrum-settings/commit/ebf7a0ed69b13e0d194bf45fd2c54dbb5dac69ff))
* **deps:** bump spatie/laravel-permission from 6.24.0 to 7.2.0 ([#18](https://github.com/GaiaTools/fulcrum-settings/issues/18)) ([45f4dac](https://github.com/GaiaTools/fulcrum-settings/commit/45f4dacdffbf9135ee2cfc2ffb954cd46f4f1c72))
* **deps:** bump spatie/laravel-permission from 7.2.0 to 7.3.0 ([0b5d68e](https://github.com/GaiaTools/fulcrum-settings/commit/0b5d68e0ad547168a7c7692205637621b6ea078d))
* **deps:** bump spatie/laravel-permission from 7.3.0 to 7.4.1 ([1f3cfa0](https://github.com/GaiaTools/fulcrum-settings/commit/1f3cfa0f33a0567ce755bfce65e95cba74ba60d1))
* **deps:** bump spatie/laravel-permission from 7.4.1 to 8.0.0 ([21179fa](https://github.com/GaiaTools/fulcrum-settings/commit/21179faa2d60a49a1d92c6c63c8159bdaae00225))
* **deps:** bump symfony/http-kernel from 7.4.8 to 7.4.13 ([1fe8a8a](https://github.com/GaiaTools/fulcrum-settings/commit/1fe8a8a08eb80998a3ab16e58303c3edcac70b2f))
* **deps:** bump symfony/mailer from 7.4.8 to 7.4.12 ([6c059e7](https://github.com/GaiaTools/fulcrum-settings/commit/6c059e7eab0773f2d8c65729514d6a930eddf6ba))
* **deps:** bump symfony/routing from 7.4.9 to 7.4.13 ([4bf9a47](https://github.com/GaiaTools/fulcrum-settings/commit/4bf9a473e77fc55fb86d0732e6da4af9ce6800ca))
* **deps:** bump symfony/yaml from 7.4.1 to 7.4.13 ([f2499ff](https://github.com/GaiaTools/fulcrum-settings/commit/f2499ff813c9be4371a0891e728ed147561a0da1))
