<?php

namespace Decaf\StandardVersion\Providers;

class Bitbucket
{
    const COMPARE_URL = 'https://bitbucket.org/#ORG#/#REPO#/branches/compare/#PREVIOUS#%0D#NEXT##diff';
    const COMMIT_URL = 'https://bitbucket.org/#ORG#/#REPO#/commit/#REF#';
}
