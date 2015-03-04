# SilverStripe Requirements Backend replacement

## Work in progress

This module is still work in progress and a hack-ish replacement for the
SilverStripe Requirements Backend.
This module exists because of current limitations in SilerStripe Requirements 
and might change its API or be completely obsolete once Requirements are
re-factored.

## Current Features

- compile sass/scss files using sassc (libsass)

## Installation

#### Requirements for using sassc

- *nix based system. Windows Servers currently not supported
- php needs to be able to shell execute commands
- php needs write permissions to the css files and the combined_files folder
- sassc needs to be installed on your system
	If your sassc executable is not in any of the paths, you can set the constant
	SS_SASSC_PATH (`define('SS_SASSC_PATH', '/path/to/sassc')`).    

#### Installing the module

1. Install the module using composer or download and place in your project folder
2. Run `?flush=1`

#### Installing sassc

If your system does not have sassc installed, you will need to install this as well.
At time of writing this, I know of no existing package or pre-compiled binary, so you either
have to [compile it yourself](http://libsass.org/#sassc) or use Zauberfisch's 
[semi-regularly build (debian)](http://easy.zauberfisch.at/vagrant/sassc) 
(this link might change in the future).

(Note: on shared web hosting it might not be possible to use sassc, but on some it will work to upload the binary to your project folder and set `define('SS_SASSC_PATH', BASE_PATH . '/sassc')`)

## Usage

By itself, this module does nothing. You will have to set the backend for Requirements.
A good place to do this is in `Page_Controller->init()` but can be at any other place as well.
Just make sure you set the backend before you use any other Requirements methods.

	class Page_Controller extends ContentController {
		// ...
		
		public function init() {
			Requirements::set_backend(new BetterRequirements_Backend());
			parent::init();
			
			// ...
		}
		
		// ...
	}

After this, you can use Requirements just the same as you did before, with one addition:
You can require sass/scss files.    
The backend will replace 'sass'/'scss' in the file extension and folder names.

	// create and require mysite/css/myfile.css
	Requirements::css(project() . '/scss/myfile.scss');
	// will create and require mysite/css/otherfile.css
	Requirements::css('mysite/css/otherfile.scss');
	// will create and require themes/mytheme/css/myfile.css
	Requirements::css($this->ThemeDir() . '/sass/myfile.sass');

#### Compiling/Preprocessing

Compiling/Preprocessing will take place when one of the following conditions is met:

- `?flush=1` (will re-compile all files)
- config `BetterRequirements_Backend.compile_in_live`
- Site is in dev mode and config `BetterRequirements_Backend.compile_in_dev` is true

You can change the configuration for this in a yml config file:

	BetterRequirements_Backend:
	  compile_in_live: true # default: false
	  compile_in_dev: true # default: true

# Notes

When compiling your sass/scss files both locally (dev) as well as on your server (eg automated after deployment), 
it is recommended to add your css files to .gitignore and exclude them from version control.

Composer is currently not compatible with sassc, however there is a workaround:	 
Composer is a mix of ruby and sass, you can use the sass part with sassc and will only loose 
a small list of features (image-height, image-width, sprite functions, ...). Get the compass mixins
from [Igosuki/compass-mixins](https://github.com/Igosuki/compass-mixins) and see Zauberfisch's
[SilverStripe Boilerplate](https://github.com/Zauberfisch/silverstripe-boilerplate/tree/dev) for example usage.

## License

	Copyright (c) 2015, Zauberfisch
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:
		* Redistributions of source code must retain the above copyright
		  notice, this list of conditions and the following disclaimer.
		* Redistributions in binary form must reproduce the above copyright
		  notice, this list of conditions and the following disclaimer in the
		  documentation and/or other materials provided with the distribution.
		* Neither the name Zauberfisch nor the names of other contributors may 
		  be used to endorse or promote products derived from this software 
		  without specific prior written permission.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.