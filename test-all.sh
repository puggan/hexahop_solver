#   1: Won:  4 | Time:        0  [0h  0m  0s] | Done:       1 831 | Cost:  33 / 33 | Speed:    14 601.94 | Memory:  0.03 GiB
echo -n "  1: "; cargo run -- debug 1 NE,2N,2NE,7SE,7SW,2NW,2N,3NE,2SE,2SW,N,SW,S 2>&1 | grep Status
#   2: Won:  4 | Time: 2 616 | Done:  49 797 983 | Cost:  49 / 49 | Speed:    19 030.46 | Memory:  0.89 GiB
echo -n "  2: "; cargo run -- debug 2 2S,NE,S,SW,NW,2N,NE,3N,NE,SE,S,SE,SW,2S,SE,NE,N,NW,2NE,N,NE,SE,S,2SW,SE,2S,SW,S,SE,NE,3N,2SE,N,NE,SE,S,SW,N, 2>&1 | grep Status
#   3; Won:  1 | Time:        0  [0h  0m  0s] | Done:       2 111 | Cost:  24 / 24 | Speed:    17 935.70 | Memory:  0.03 GiB
echo -n "  3: "; cargo run -- debug 3 S,SE,S,SE,NE,SE,NE,N,NE,N,NW,N,NW,SW,S,SE,S,SW,NW,N,NW,N,2NE 2>&1 | grep Status
#   4: Won:  1 | Time:        0  [0h  0m  0s] | Done:         290 | Cost:  26 / 26 | Speed:     6 638.14 | Memory:  0.02 GiB
echo -n "  4: "; cargo run -- debug 4 SE,NE,2S,NE,SE,2S,2NW,S,SW,SE,NE,2SE,NE,NW,S,NE,N,2NW,NE,SW,NW 2>&1 | grep Status
#   5: Won:  6 | Time:       12  [0h  0m 12s] | Done:     130 643 | Cost:  52 / 52 | Speed:    10 600.69 | Memory:  0.14 GiB
echo -n "  5: "; cargo run -- debug 5 NE,S,3SE,N,SE,S,SE,2N,SE,2NE,N,2NW,SW,NW,NE,N,2SW,S,NW,SW,N,NE,2NW,2S,2SE,SW,S,SW,2S,2NE,NW,N,3NE,S,2NE,SE,SW,NW 2>&1 | grep Status
#   6: Won:  2 | Time:        2  [0h  0m  2s] | Done:      39 505 | Cost:  34 / 34 | Speed:    15 082.31 | Memory:  0.11 GiB
echo -n "  6: "; cargo run -- debug 6 2SE,S,2SE,2NE,SE,N,2S,3NE,NW,3N,NE,3NW,S,SW,2NW,2SW,S,SW,SE,2N,S 2>&1 | grep Status
#   7: Won:  1 | Time:        3  [0h  0m  3s] | Done:      59 249 | Cost:  37 / 37 | Speed:    15 066.15 | Memory:  0.12 GiB
echo -n "  7: "; cargo run -- debug 7 NW,N,NW,N,NE,NW,2SW,SE,S,3NE,NW,N,NE,SE,2S,2SW,3SE,S,SE,NE,N,2NW,NE,N,NW,SW,3S 2>&1 | grep Status
#   8: Won:  2 | Time:        1  [0h  0m  1s] | Done:      21 227 | Cost:  22 / 22 | Speed:    17 944.61 | Memory:  0.08 GiB
echo -n "  8: "; cargo run -- debug 8 NE,SE,NE,2SE,3N,3SW,NE,SE,NE,2NW,NE,5S 2>&1 | grep Status
#   9: Won:  1 | Time:       13  [0h  0m 13s] | Done:     189 588 | Cost:  30 / 30 | Speed:    14 483.54 | Memory:  0.15 GiB
echo -n "  9: "; cargo run -- debug 9 SW,2SE,SW,NW,S,SE,NE,S,SW,SE,NE,N,SE,NE,SE,S,NW,S,2NW,SE,NE,NW,SW,NW,2NE,NW,SW 2>&1 | grep Status
#  10: Won:  1 | Time: 1 769 | Done:  24 970 275 | Cost: 124 / 124 | Speed:    14 113.53 | Memory:  0.49 GiB
echo -n " 10: "; cargo run -- debug 10 SE,2NE,SE,S,N,NW,2SW,S,2SE,2NE,NW,SE,3NE,S,2SW,S,SE,N,SE,NE,NW,NE,N,NE,2SE,N,NW,SW,NW,N,NE,NW,N,NE,SW,NW 2>&1 | grep Status
#  11: Won:  7 | Time: 9 507 | Done:  71 817 461 | Cost: 361 / 361 | Speed:     7 553.43 | Memory:  1.57 GiB
echo -n " 11: "; cargo run -- debug 11 N,NE,N,4NE,SE,NW,4SW,S,SW,4S,SE,NE,SE,6NE,3N,3NW,3SE,3S,6SW,NW,SW,NW,2N,NE,SE,NW,SE,2NE,SW,2NE,NW 2>&1 | grep Status
#  12: # TODO OK?, need reachable high-greens
echo -n " 12: "; cargo run -- debug 12 N,S,N,SE,N,2SE,NW,NE,2N,2SW,NE,NW,2N,2SE,NW,NE,SE,NE,SW,SE,4S,SW,S,2SE,2NE,2NW,SW,NW,N 2>&1 | grep Status
#  13: Won:  5 | Time:        2  [0h  0m  2s] | Done:      37 488 | Cost: 101 / 101 | Speed:    13 542.14 | Memory:  0.10 GiB
echo -n " 13: "; cargo run -- debug 13 2SW,2S,SW,3NW,SW,NE,3SE,NE,SE,S,N,NE,SE,S,N,NW,N,NE,SE,NE,N,NW,SW,NW,N,SW,NW,S,2SW,NW,N,2NE,S 2>&1 | grep Status
#  14: Won:  1 | Time:       28  [0h  0m 28s] | Done:     277 148 | Cost:  63 / 63 | Speed:     9 673.92 | Memory:  0.15 GiB
echo -n " 14: "; cargo run -- debug 14 S,SE,S,2NE,NW,NE,N,NW,SW,N,NE,2SE,S,3NE,SE,S,NW,S,2NW,S,3N,2NE,3SE,S,NE,SE,S,SW,NW,S,3SW,2NW,S,SW,SE,NE,SE,3SW,NW,N,SW,2NW,4N,S 2>&1 | grep Status
#  15: # TODO OK, Need reachable high-greens
#  16: # TODO OK, Need reachable high-greens
#  17: # TODO OK, -- OOM 5+ GB
#  18: Won:  2 | Time:         4  [0h  0m  4s] | Done:      78 567 | Cost:  68 / 68 | Speed:    17 457.36 | Memory:  0.13 GiB
echo -n " 18: "; cargo run -- debug 18 SE,NE,SE,2SW,NW,2N,NE,SE,NE,N,NE,SE,S,SE,NE,N,NE,S,2SW,N,NE,NW,SW,S,SE 2>&1 | grep Status
#  19: Won: 10 | Time:        0  [0h  0m  0s] | Done:       8 313 | Cost:  81 / 81 | Speed:    15 372.37 | Memory:  0.05 GiB
echo -n " 19: "; cargo run -- debug 19 2N,S,2N,NE,SW,NE,2SE,NW,S,SE,S,NE,NW,SW,NE,2SW,NW 2>&1 | grep Status
#  20: Won:  3 | Time:        1  [0h  0m  1s] | Done:      14 292 | Cost:  32 / 32 | Speed:    13 970.40 | Memory:  0.06 GiB
echo -n " 20: "; cargo run -- debug 20 2SE,3NE,N,SE,SW,S,SE,NE,N,NE,N,2NW,2SW,NE,N,NW,SW,S,SW,S,SE,2S,2SE,S,SW 2>&1 | grep Status
#  21: Won:  2 | Time:        47  [0h  0m 47s] | Done:     566 881 | Cost:  51 / 51 | Speed:    12 056.27 | Memory:  0.15 GiB
echo -n " 21: "; cargo run -- debug 21 NW,SW,2NW,SE,NW,SW,4NW,5SE,N,SE,N,SE,N,S,N,SE,NE,NW,SE,N,S,N,SE,NE,NW,NE,3N,SW,NW,SW,NW 2>&1 | grep Status
#  22: Won:  2 | Time:        72  [0h  1m 12s] | Done:   1 257 298 | Cost: 144 / 144 | Speed:    17 324.40 | Memory:  0.16 GiB
echo -n " 22: "; cargo run -- debug 22 N,S,3N,NE,SW,3NE,SW,NE,2SE,NW,SE,S,SE,SW,NW,SE,NE,SW,NW,N,S,N,NE,SE,NW,SE,S,N,S 2>&1 | grep Status
#  23: Won:  7 | Time:         0  [0h  0m  0s] | Done:      26 273 | Cost:  20 / 20 | Speed:    30 659.78 | Memory:  0.09 GiB
echo -n " 23: "; cargo run -- debug 23 2NE,SE,S,2NE,2N,SE,NW,SW,S,SE,S,SW,SE,2NE,2N 2>&1 | grep Status
#  24: Won:  1 | Time:         0  [0h  0m  0s] | Done:      10 488 | Cost: 187 / 187 | Speed:    24 104.50 | Memory:  0.05 GiB
echo -n " 24: "; cargo run -- debug 24 N,SW,S,SE,NE,SE,S,SE,NE,SE,NE,SE,NE,N,NW,SE,NW,N,NW,N,NE,N,NE,SE,NW,SE,NE 2>&1 | grep Status
#  25: # TODO "LASER",
#  26: # TODO "LASER",
#  27: Won:  1 | Time:        0  [0h  0m  0s] | Done:       2 770 | Cost:  29 / 29 | Speed:    11 352.20 | Memory:  0.03 GiB
echo -n " 27: "; cargo run -- debug 27 4SE,NE,3N,4NW,SW,N,SE,NW,SW,S,4SE,NE,N,NE,N,3NW 2>&1 | grep Status
#  28: # TODO "LASER",
#  29: # TODO "LASER",
#  30: Won:  1 | Time:    2 732  [0h 45m 32s] | Done:  38 010 769 | Cost:  57 / 57 | Speed:    13 911.06 | Memory:  0.89 GiB
echo -n " 30: "; cargo run -- debug 30 2NW,SE,2S,2NE,N,NW,2NE,2SE,2SW,SE,NE,SW,2S,NW,SW,SE,NW,2SW,N,2NW,SW,NE,SE,NE,S,SE,2N 2>&1 | grep Status
#  31: Won:  1 | Time:         0  [0h  0m  0s] | Done:         942 | Cost:  29 / 29 | Speed:     7 776.38 | Memory:  0.02 GiB
echo -n " 31: "; cargo run -- debug 31 S,SW,SE,NE,S,SW,3SE,N,S,2NE,NW,SE,2N,2NE,S,SE,2S,NE,SE,NW,2SW,S 2>&1 | grep Status
#  32: Won:  1 | Time:         0  [0h  0m  0s] | Done:         632 | Cost:  20 / 20 | Speed:    11 862.79 | Memory:  0.02 GiB
echo -n " 32: "; cargo run -- debug 32 NE,N,NW,2SE,S,2N,SW,NW,SE,NE,S,2NW,SE,SW,SE,NE,NW 2>&1 | grep Status
#  33: Won:  2 | Time:         0  [0h  0m  0s] | Done:       1 533 | Cost:  32 / 32 | Speed:    10 348.06 | Memory:  0.02 GiB
echo -n " 33: "; cargo run -- debug 33 3SE,NE,SW,S,N,3NW,3N,NW,SE,S,NE,2NW,SE,3S,4SW,NE,SW,2NE,SW 2>&1 | grep Status
#  34: Won:  1 | Time:         0  [0h  0m  0s] | Done:      17 476 | Cost:  34 / 34 | Speed:    27 785.40 | Memory:  0.07 GiB
echo -n " 34: "; cargo run -- debug 34 SW,NW,4N,NE,SE,S,4SW,3NW,NE,SE,NE,3N,NW,SW,2S,SE,NE,2N,NW,2SW,S 2>&1 | grep Status
#  35: # TODO OK, -- OOM 12 GB
#  36: # TODO "ROTATOR, LASER",
#  37: Won:  1 | Time:         0  [0h  0m  0s] | Done:         270 | Cost:  19 / 19 | Speed:    13 451.48 | Memory:  0.02 GiB
echo -n " 37: "; cargo run -- debug 37 2SE,NW,N,S,N,S,N,S,NW,N,2NE,SE,4S,NW 2>&1 | grep Status
#  38: Won:  2 | Time:         0  [0h  0m  0s] | Done:      10 480 | Cost:  51 / 51 | Speed:    14 890.54 | Memory:  0.05 GiB
echo -n " 38: "; cargo run -- debug 38 NE,SW,NE,SW,NE,N,NW,S,NE,S,NE,S,2SW,S,NE,SW,2NE,SE,NE,N,SE,S,SW,2S,SW,NW,2S 2>&1 | grep Status
#  39: Won:  2 | Time:       300  [0h  5m  0s] | Done:   6 711 306 | Cost:  41 / 41 | Speed:    22 309.48 | Memory:  0.19 GiB
echo -n " 39: "; cargo run -- debug 39 SE,NE,SE,S,SE,2NE,2N,NW,2S,SW,2NW,2SW,2NW,2N,S,SW,SE,NE,N,NE,2SE,2S,SW,2N,NW,2SW,2NW,SW,N 2>&1 | grep Status
#  40: Won:  1 | Time:         0  [0h  0m  0s] | Done:       3 493 | Cost:  16 / 16 | Speed:    13 980.93 | Memory:  0.03 GiB
echo -n " 40: "; cargo run -- debug 40 SE,2SW,N,NE,N,NE,3SE,SW,NW,SW,S,SE,NW 2>&1 | grep Status
#  41: # TODO "ROTATOR",
#  42: Won:  5 | Time:       342  [0h  5m 42s] | Done:   3 265 789 | Cost:  41 / 41 | Speed:     9 521.38 | Memory:  0.18 GiB
echo -n " 42: "; cargo run -- debug 42 2SW,NW,S,SW,N,SE,SW,2NE,3SE,NW,2SE,SW,2NW,N,SE,NW,SE,2NW,3N,2NE,SE,S,SW,N,2S,N,NW,SW,S,SE 2>&1 | grep Status
#  43: Won:  2 | Time:         6  [0h  0m  6s] | Done:     132 311 | Cost:  76 / 76 | Speed:    20 344.15 | Memory:  0.14 GiB
echo -n " 43: "; cargo run -- debug 43 3SE,NE,SW,S,SE,NE,2N,SE,4S,NW,S,3SW,NW,N,2SW,2SE 2>&1 | grep Status
#  44: Won:  6 | Time:         7  [0h  0m  7s] | Done:     132 131 | Cost:  45 / 45 | Speed:    16 913.38 | Memory:  0.14 GiB
echo -n " 44: "; cargo run -- debug 44 SE,N,S,N,NE,SE,SW,N,SE,2NE,S,SW,S,SW,S,SE,N,NE,S,NE,NW,N,2SE, 2>&1 | grep Status
#  45: # TODO "ROTATOR, LASER",
#  46: Won:  8 | Time:         0  [0h  0m  0s] | Done:       1 326 | Cost:  50 / 50 | Speed:    16 440.24 | Memory:  0.02 GiB
echo -n " 46: "; cargo run -- debug 46 SE,NE,SE,S,SW,2NW,NE,N,NW,SE,2NW,2SW,S,N,S,SE,NW 2>&1 | grep Status
#  47: Won:  1 | Time:    16 161  [4h 29m 21s] | Done: 137 748 811 | Cost:  85 / 85 | Speed:     8 523.10 | Memory:  3.32 GiB
echo -n " 47: "; cargo run -- debug 47 3S,2NE,SE,N,NE,3S,2SW,N,NE,NW,N,SE,N,2SW,SE,NW,NE,SE,NE,SW,2S,2N,NW,3SW,SE,2SW,NE,SW,4NW,SE 2>&1 | grep Status
#  48: Won:  1 | Time:         0  [0h  0m  0s] | Done:       9 326 | Cost:  27 / 27 | Speed:    15 131.55 | Memory:  0.05 GiB
echo -n " 48: "; cargo run -- debug 48 SE,2NE,SE,3NE,NW,SW,N,NW,SW,S,NW,3N,SE,NE,2NW,3SW,S,N,S 2>&1 | grep Status
#  49: # TODO "ICE", -- OOM? 8GB
#  50: # TODO "ROTATOR, LASER, ICE, ITEM_ANTI_ICE",
#  51: # TODO "ROTATOR, LASER, ICE, ITEM_ANTI_ICE",
#  52: # TODO "ROTATOR, LOW_ELEVATOR",
#  53: Won:  8 | Time:     5 032  [1h 23m 52s] | Done:  59 127 134 | Cost:  82 / 82 | Speed:    11 748.09 | Memory:  1.78 GiB
echo -n " 53: "; cargo run -- debug 53 2NE,2SE,SW,N,SE,2SW,2NW,2SW,S,NE,N,2NE,SE,NE,2N,S,NE,SE,N,2S,N,SE,SW,N 2>&1 | grep Status
#  64: Won:  2 | Time:         8  [0h  0m  8s] | Done:     122 699 | Cost:  62 / 62 | Speed:    14 869.62 | Memory:  0.14 GiB
echo -n " 54: "; cargo run -- debug 54 NE,S,NE,SE,N,3SE,2S,2N,NW,N,NE,2SW,NW,SW,NW,NE,S 2>&1 | grep Status
#  55: # TODO "ROTATOR, LASER",
#  56: Won:  4 | Time:        54  [0h  0m 54s] | Done:   1 105 949 | Cost:  39 / 39 | Speed:    20 138.14 | Memory:  0.15 GiB
echo -n " 56: "; cargo run -- debug 56 2S,SW,3S,3NE,3SE,NE,2N,NW,NE,N,SE,N,3NW,SW,NW,SW,SE,S,SW,2SE,NE,2S,SE,N,NW,SW,NE 2>&1 | grep Status
#  57: # TODO "BUILD, LOW_ELEVATOR",
#  58: Won:  1 | Time:     2 734  [0h 45m 34s] | Done:  32 949 852 | Cost:  45 / 45 | Speed:    12 049.75 | Memory:  0.87 GiB
echo -n " 58: "; cargo run -- debug 58 5N,SE,N,NE,S,NE,N,4SE,S,3SW,S,SW,SE,NE,S,SE,N,SE,5NE,3N,SW,3S,SE,S,SE,N,NE,SW 2>&1 | grep Status
#  59: # TODO "ROTATOR, LASER, ICE",
#  60: # TODO "BUILD",
#  61: # TODO "ROTATOR, LASER, ICE, ITEM_ANTI_ICE",
#  62: Won:  1 | Time:         7  [0h  0m  7s] | Done:     138 287 | Cost:  37 / 37 | Speed:    17 704.86 | Memory:  0.14 GiB
echo -n " 62: "; cargo run -- debug 62 2NW,NE,NW,2S,NW,N,NW,NE,3NW,5N,NE,SE,3S,NW,SE,S,N,2NE,N,NE,SE,S,SW,SE,S,SW 2>&1 | grep Status
#  63: # TODO "LOW_ELEVATOR",
#  64: # TODO "ROTATOR, LASER",
#  65: # TODO "ROTATOR, BUILD",
#  66: # TODO "ITEM_JUMP", -- OOM
#  67: # TODO "LOW_ELEVATOR",
#  68: Won:  1 | Time:       183  [0h  3m  3s] | Done:   2 628 991 | Cost: 116 / 116 | Speed:    14 330.49 | Memory:  0.16 GiB
echo -n " 68: "; cargo run -- debug 68 N,2S,NE,SW,3S,5NE,NW,N,NW,2SW,NW,SE,S,2SE,3NE,SW,3NE,S,2SE,SW,N,SE 2>&1 | grep Status
#  69: # TODO "LOW_ELEVATOR",
#  70: # TODO "BUILD, LOW_ELEVATOR",
#  71: # TODO "ROTATOR, ICE, ITEM_ANTI_ICE",
#  72: # TODO "LASER, ITEM_JUMP",
#  73: # TODO "ROTATOR, LASER, ITEM_JUMP",
#  74: # TODO "ROTATOR, ITEM_JUMP",
#  75: # TODO "LASER, ICE, BUILD",
#  76: # TODO "ROTATOR, LASER",
#  77: Won:  5 | Time:         0  [0h  0m  0s] | Done:       3 828 | Cost:  18 / 18 | Speed:    20 522.96 | Memory:  0.03 GiB
echo -n " 77: "; cargo run -- debug 77 N,NW,2S,NE,S,SE,S,SW,NW,N,NE,N,SE,2N,2NW, 2>&1 | grep Status
#  78: Won:  3 | Time:         4  [0h  0m  4s] | Done:      58 877 | Cost:  38 / 38 | Speed:    14 335.34 | Memory:  0.12 GiB
echo -n " 78: "; cargo run -- debug 78 N,NE,SE,2NW,N,SE,SW,NW,SE,N,NW,SE,NE,N,2SE,SW,SE,NE,S,NW,SW,S,SE,SW,NW,N,NW,N,SE,2NE,N,SE,2S,SW 2>&1 | grep Status
#  79: Won:  1 | Time:         0  [0h  0m  0s] | Done:          80 | Cost:  11 / 11 | Speed:     3 972.82 | Memory:  0.02 GiB
echo -n " 79: "; cargo run -- debug 79 2NE,SW,S,N,NW,SW,S,SE,2S 2>&1 | grep Status
#  80: # TODO "LASER, ICE",
#  81: # TODO "ROTATOR, BUILD, LOW_ELEVATOR, ITEM_JUMP",
#  82: Won:  7 | Time:        21  [0h  0m 21s] | Done:     461 432 | Cost:  20 / 20 | Speed:    21 961.69 | Memory:  0.15 GiB
echo -n " 82: "; cargo run -- debug 82 NE,N,2NE,3S,NW,SW,NW,N,SE,SW,NW,N,SW,3S,NE 2>&1 | grep Status
#  83: # TODO "LASER, ICE, LOW_ELEVATOR, HIGH_ELEVATOR",
#  84: # TODO "LASER, LOW_ELEVATOR, HIGH_ELEVATOR, ITEM_JUMP",
#  85: Won:  8 | Time:        31  [0h  0m 31s] | Done:     585 673 | Cost:  33 / 33 | Speed:    18 338.65 | Memory:  0.15 GiB
echo -n " 85: "; cargo run -- debug 85 NE,3SE,S,SE,NE,N,2NW,2N,NE,2SE,SW,N,3SE,S,2SW,NE,2SW,N,NW,2NE,NW,2SW 2>&1 | grep Status
#  86: # TODO "LOW_ELEVATOR",
#  87: # TODO "ROTATOR, LOW_ELEVATOR, HIGH_ELEVATOR",
#  88: Won:  1 | Time:         3  [0h  0m  3s] | Done:      47 789 | Cost:  38 / 38 | Speed:    13 804.07 | Memory:  0.12 GiB
echo -n " 88: "; cargo run -- debug 88 SE,NE,2N,S,NW,SE,SW,2NW,NE,2N,NE,SE,S,NW,J 2>&1 | grep Status
#  89: # TODO "ROTATOR, LASER, ICE, ITEM_ANTI_ICE",
#  90: # TODO "ROTATOR, LASER, LOW_ELEVATOR",
#  91: # TODO "BUILD",
#  92: # TODO OK, OOM? 5GB
#  93: # TODO "ROTATOR, LASER, ICE",
#  94: Won:  1 | Time:       271  [0h  4m 31s] | Done:   3 453 681 | Cost:  37 / 37 | Speed:    12 716.70 | Memory:  0.17 GiB
echo -n " 94: "; cargo run -- debug 94 NE,N,NE,S,NE,N,NE,N,SE,N,NE,NW,SW,NW,N,NE,3SE,2S,2SW,NW,N,NE,NW,SW,NW,N,NW,SW,S,SE,3S 2>&1 | grep Status
#  95: # TODO "ROTATOR",
#  96: # TODO "ROTATOR, LASER, ICE, ITEM_ANTI_ICE",
#  97: Won:  2 | Time:       100  [0h  1m 40s] | Done:   1 362 765 | Cost:  35 / 35 | Speed:    13 566.07 | Memory:  0.15 GiB
echo -n " 97: "; cargo run -- debug 97 2SW,2SE,NW,NE,2N,S,SE,NW,2SW,4S,SE,NE,NW,2N,NW,S,3SE,N,NW,3N,NW,2SW 2>&1 | grep Status
#  98: Won:  2 | Time:        51  [0h  0m 51s] | Done:     961 034 | Cost:  30 / 30 | Speed:    18 815.54 | Memory:  0.15 GiB
echo -n " 98: "; cargo run -- debug 98 N,3NE,S,NE,SE,S,N,NW,2SW,2N,SW,S,SW,SE,S,SE,N,NE,SE,NE,SW,NW,S,3NW 2>&1 | grep Status
#  99: # TODO "ROTATOR, BUILD",
# 100: Won:  4 | Time:         0  [0h  0m  0s] | Done:       8 135 | Cost:  33 / 33 | Speed:    11 116.64 | Memory:  0.05 GiB
echo -n "100: "; cargo run -- debug 100 NE,2SE,3NE,2N,S,SE,SW,2N,NW,N,SE,NW,N,NE,SE,S,NW,2S,2N,NW,2SW,3S,SE 2>&1 | grep Status
