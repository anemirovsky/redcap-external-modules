**PULL REQUESTS:** Please create pull requests against the **testing** branch.

# REDCap External Modules

This repository represents the development work for the REDCap External Modules framework, which is a class-based framework for plugins and hooks in REDCap. External Modules is an independent and separate piece of software from REDCap, and is included natively in REDCap 8.0.0 and later.

## Installation
Clone this repo into a directory explicitly named **external_modules** under your REDCap web root (e.g., /redcap/external_modules/). NOTE: If you are currently running REDCap 8.0.0+, then REDCap will utilize the code in the /redcap/external_modules/ directory rather than the embedded one in /redcap/redcap_vX.X.X/ExternalModules/.

## Usage

The best way to get started might be by learning by example by downloading an [example module here](https://github.com/mmcev106/redcap-external-module-example). It can be installed by downloading that repo as a ZIP file, then extracting it's contents to `<redcap-web-root>/modules/vanderbilt_example_v1.0/`.

**You should also reference the [Official External Modules Documentation](docs/official-documentation.md) if you are building a module.**
