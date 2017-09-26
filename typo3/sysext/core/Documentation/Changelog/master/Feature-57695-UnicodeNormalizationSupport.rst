.. include:: ../../Includes.txt

===============================================================================================================
Feature: #57695 - Enhance charset conversion with unicode-normalization support and add some new UTF-8 features
===============================================================================================================

See :issue:`57695`

Description
===========

Preface
-------

We all know that the TYPO3 Core's unicode (utf8) charset handling is quite stable. And we +don't+ suspect the
charset-conversion to be broken. It is just incomplete. The following explanations refer to the documentation
of "intl"-extension's Normalizer_ implementation and the related `unicode normalization forms`_. We refer to the
normalization forms as *NFC*, *NFD*, *NFKC* and *NFKD*.

The Missing Feature
-------------------
 
If you move the a TYPO3 Installation between hosts with varying UTF8-capable filesystems which are based upon
different `unicode normalization forms`_ it may trigger FAL's local filesystem driver indexing for existing files
as completely new files, because their file path unicode normalization form has changed. To reproduce this
behavior one has to create a file or folder containing the german umlaut "ü" in a TYPO3-Installation on 
Linux/Windows and move the whole Installation to a Mac and vice-versa. Depending on the `method used to transfer
the files`_ from one host to the other, a different unicode normalization may be available, leading to different
file-paths and file-identifier hashes. This behavior made sense as TYPO3 ignores unicode normalization and it is
currently not designed to be moved between such host constellations back and forth.

Proposal
--------

Focusing on a solution for the filesystem, TYPO3 implements support for three unicode normalization strategies:

# No decomposition/re-composition of file- and folder-identifiers (this is what we currently have)
# normalize file- and folder-identifiers to NFC
# normalize file- and folder-identifiers to NFD (or a NFD variant used by Apple™ on HFS+ filesystems)

With the help of the existing Normalizer_ implementation plus the existing fallbacks `patchwork/utf8`_ and 
`symfony/polyfill-intl-normalizer`_ we implement the three normalization strategies. Then we define one to be the
default - the one that ensures consistent behavior across all supported platforms. An implementation to convert
file paths and/or their hashs between the strategies on the supported operating- and filesystems should be added.
Finally unicode-normalization should be integrated into the core's charset encoding processes in general, leading
to a consistent and stable design for dealing with utf-8 from user-generated contents.

Notes and research findings
---------------------------

*NFKC* and *NFKD* do not play an important role for us, at least not on filesystem level. *NFC* has advantages as
well as disadvantages to *NFD*. None of these four representation forms seems to be far superior for all use cases. 

Strategy: No decomposition/re-composition
`````````````````````````````````````````

* Means any normalization form is supported, as nothing gets touched at all.
* Most filesystems on most linux and BSD setups deal with filenames and -paths as binary data. This allows creation
  of visually identical looking file-paths using different normalizations. In FAL these file-paths differ in their
  file-identifier hashes.
* Mixed normalized paths occured very often in the past (try searching the web):
  - Example: Improperly configured samba-machines serving shares mounted by MacOS/OSX and Windows at the same time.
    For Germans an umlaut like "ü" drove some people insane.
* To my (the author's) experience linux software itself always produces *NFC* normalization, but sometimes one of
  the other ones may occur. It depends on the behavior of the involved filesystems, tools, clients and services.
* A lot of software projects started with this strategy and fixed related issues later on by enforcing a particular
  normalization form. Many projects have choosen *NFC*.

Strategy: Everything normalized to *NFC*
````````````````````````````````````````

   “A normalization form that erases any canonical differences, and generally produces a composed result. For
   example, a + umlaut is converted to ä in this form. This form most closely matches legacy usage.”

   -- quoted from unicode glossary on `normalization form c`_

* Characters are decomposed and then re-composed by canonical equivalence.
* Microsoft Windows is using NFC per default and claims to support the three other ones too. This still needs
  verification, as I (the author) lack Windows installations.
* Its the W3C's recommendation for HTML5 output (and a requirement for a HTML5 compatible parser).
* Costs higher rendering resource usage for many Asian languages
* Saves some rendering resources for most western languages

Strategy: Everything normalized to *NFD*
````````````````````````````````````````

   “A normalization form that erases any canonical differences, and produces a decomposed result. For example, ä
   is converted to a + umlaut in this form. This form is most often used in internal processing, such as in
   collation.”

   -- quoted from unicode glossary on `normalization form d`_
  
* Characters are decomposed by canonical equivalence, and multiple combining characters are arranged in a specific
  order.
* Apple's `HFS+ filesystem uses NFD`_ (or at least something that matches it very closely). Various solutions for
  their normalization exist, and is often called "utf8-mac" encoding. Naming it encoding is actually wrong as it
  is a different representation.
* Provides higher potential for really fast sorting implementations
* Costs higher rendering resource usage for most western languages
* Saves some rendering resources for many Asian languages

Impact
======

The impact of (a stable and working) unicode normalization awareness is …

* Depending on the configuration this implementation may or may not add some overhead to the processing of files,
  frontend-output and user-inputs in general.
* The current implementation is designed to be fully backwards compatible and should not change anything in a
  running installation, unless deliberately configured to do so
* If configuration of TYPO3_CONF_VARS[SYS][UTF8filesystem] has been set to something higher than 1 (NONE), an
  install-tool wizard performs the steps necessary to re-calculate all file-identifier hashs
* As TYPO3 allows to use the utf-8 for filenames, reliability can be enhanced by configuration. A properly
  configured installation can then be moved between different filesystems and operating systems, without
  surprises. TYPO3 can now really *encourage* the use of utf-8 for filenames.
* As the current implementation allows normalizing or detection of normalization state for all kinds of user-input,
  like urls or POST data, security can be enhanced by configuration. This can harden against attacks with malicious
  strings or poisoning (url-)caches with identical looking (url-)strings.
* Unicode normalization support can only be used, if one of the three composer suggestions are installed or if a
  custom implementation has been configured. Installing the "iconv"-extension on OS X will add the option to use
  Apple™'s NFD-variant on HFS+ filesystems. The install-tool provides checks to guide the user.

Documentation
=============

Core-Configuration
------------------

Core-API
--------

Installation
------------

File-Abstraction-Layer
----------------------

Frontend
--------

.. index:: FAL, Backend, Frontend, LocalConfiguration
.. _Normalizer: http://www.php.net/manual/en/class.normalizer.php
.. _unicode normalization forms: http://en.wikipedia.org/wiki/Unicode_equivalence
.. _patchwork/utf8: https://packagist.org/packages/patchwork/utf8
.. _symfony/polyfill-intl-normalizer: https://packagist.org/packages/symfony/polyfill-intl-normalizer
.. _normalization form c: http://www.unicode.org/glossary/#normalization_form_c
.. _normalization form d: http://www.unicode.org/glossary/#normalization_form_d
.. _HFS+ filesystem uses NFD: https://en.wikipedia.org/wiki/HFS_Plus#Design
.. _method used to transfer the files: https://serverfault.com/questions/397420/converting-utf-8-nfd-filenames-to-utf-8-nfc-in-either-rsync-or-afpd/427200#427200
