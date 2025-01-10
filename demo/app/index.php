<?php

usleep(random_int(1_000, 200_000));
echo 'hello from ' . gethostname();
