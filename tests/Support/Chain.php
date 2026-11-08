<?php

// -----------------------------------------------------------
//  [*] Chain — the blocks of the chain the stand-in node has
//
//  A chain built the way bitlitasd builds its own, so
//  the figures the app works out are the real kind: block 0
//  pays GENESIS_TX's 17.592186044415 LIT, every later block
//  pays the emission curve of cryptonote_config.h for
//  one-minute blocks — (2^64 − 1 − coins so far) >> 20, never
//  under the 0.3 LIT tail — and the blocks are a minute
//  apart from 2018-01-15 22:33:55 UTC, when block 1 was
//  mined. Its headers carry every field get_block_headers_range
//  answers with on a v0.18 node.
//
//  Used by:
//    - NodeEmulator (this folder) — the headers it answers with
//    - World (this folder) — blocks stored before a test
//    - the Feature and Server suites — the rewards they expect
// -----------------------------------------------------------


namespace Tests\Support;








// -----------------------------------------------------------
// Chain
// -----------------------------------------------------------
//
// Static and deterministic: a height always has the same
// reward, time and hash. The rewards are worked out once,
// in order, and kept.
//
//   reward    — the atomic units block h pays its miner
//   timestamp — when block h was mined
//   hash      — block h's hash
//   header    — block h as get_block_headers_range lists it
//   headers   — blocks from to to, oldest first
//   rewardSum — the atomic units blocks from to to pay
//
// Used by:
//   - NodeEmulator, World (this folder)
//   - the Feature and Server suites
// -----------------------------------------------------------

class Chain
{

    // What GENESIS_TX pays: (2^64 − 1) >> 20 atomic units
    public const GENESIS_REWARD = 17592186044415;

    // The floor of every reward: FINAL_SUBSIDY_PER_MINUTE for
    // one-minute blocks, 0.3 LIT
    public const TAIL_REWARD = 300000000000;

    // When block 1 was mined; block 0 carries time 0
    public const FIRST_BLOCK_TIME = 1516055635;

    // Atomic units per LIT (CRYPTONOTE_DISPLAY_DECIMAL_POINT 12)
    public const ATOMIC_UNITS = 1000000000000;

    // The difficulty every block after the genesis block
    // reports
    public const DIFFICULTY = 1160;

    // The rewards worked out so far, by height, and the coins
    // generated before the next one
    private static $rewards = [];
    private static $generated = 0;






    // -----------------------------------------------------------
    // reward
    // -----------------------------------------------------------
    //
    // (2^64 − 1 − generated) >> 20 in PHP's signed 64 bits:
    // 2^64 − 1 − g is (2^63 − 1 − g) + 2^63, and 2^63 >> 20 is
    // 2^43, so the shift splits into two that fit.
    //
    // Used by:
    //   - header, rewardSum (below)
    //   - the Feature and Server suites
    // -----------------------------------------------------------

    public static function reward(int $height): int
    {
        while (count(self::$rewards) <= $height) {
            $reward = max(((PHP_INT_MAX - self::$generated) >> 20) + (1 << 43), self::TAIL_REWARD);
            self::$rewards[] = $reward;
            self::$generated += $reward;
        }
        return self::$rewards[$height];
    }






    // -----------------------------------------------------------
    // timestamp
    // -----------------------------------------------------------
    //
    // 0 for the genesis block, as in every Monero-born chain;
    // one minute per block from block 1 on.
    //
    // Used by:
    //   - header (below)
    //   - the Feature suite
    // -----------------------------------------------------------

    public static function timestamp(int $height): int
    {
        return $height === 0 ? 0 : self::FIRST_BLOCK_TIME + 60 * ($height - 1);
    }






    // -----------------------------------------------------------
    // hash
    // -----------------------------------------------------------
    //
    // A 64-digit hexadecimal hash, unique to the height.
    //
    // Used by:
    //   - header (below)
    //   - NodeEmulator (this folder) — the top block's hash
    // -----------------------------------------------------------

    public static function hash(int $height): string
    {
        return hash('sha256', "bitlitas test block $height");
    }






    // -----------------------------------------------------------
    // header
    // -----------------------------------------------------------
    //
    // Block h of a chain whose top block is $top, with the
    // fields of v0.18's block_header_response, holding no
    // transaction but its miner's.
    //
    // Used by:
    //   - headers (below)
    //   - NodeEmulator::block (this folder)
    // -----------------------------------------------------------

    public static function header(int $height, int $top): array
    {
        $difficulty = $height === 0 ? 1 : self::DIFFICULTY;
        $cumulative = 1 + self::DIFFICULTY * $height;

        return [
            'major_version'               => 1,
            'minor_version'               => 0,
            'timestamp'                   => self::timestamp($height),
            'prev_hash'                   => $height === 0 ? str_repeat('0', 64) : self::hash($height - 1),
            'nonce'                       => $height === 0 ? 10000 : $height,
            'orphan_status'               => false,
            'height'                      => $height,
            'depth'                       => $top - $height,
            'hash'                        => self::hash($height),
            'difficulty'                  => $difficulty,
            'wide_difficulty'             => '0x' . dechex($difficulty),
            'difficulty_top64'            => 0,
            'cumulative_difficulty'       => $cumulative,
            'wide_cumulative_difficulty'  => '0x' . dechex($cumulative),
            'cumulative_difficulty_top64' => 0,
            'reward'                      => self::reward($height),
            'block_size'                  => 80,
            'block_weight'                => 80,
            'num_txes'                    => 0,
            'pow_hash'                    => '',
            'long_term_weight'            => 80,
            'miner_tx_hash'               => hash('sha256', "bitlitas test miner transaction $height"),
        ];
    }






    // -----------------------------------------------------------
    // txHash
    // -----------------------------------------------------------
    //
    // The hash of transaction $index of block $height, made up
    // and unique.
    //
    // Used by:
    //   - NodeEmulator (this folder)
    //   - the Feature suite — the transactions it expects asked
    //     for
    // -----------------------------------------------------------

    public static function txHash(int $height, int $index): string
    {
        return hash('sha256', "bitlitas test transaction $height $index");
    }






    // -----------------------------------------------------------
    // headers
    // -----------------------------------------------------------
    //
    // Blocks $from to $to, both included, oldest first.
    //
    // Used by:
    //   - NodeEmulator (this folder)
    // -----------------------------------------------------------

    public static function headers(int $from, int $to, int $top): array
    {
        $headers = [];
        for ($height = $from; $height <= $to; $height++) {
            $headers[] = self::header($height, $top);
        }
        return $headers;
    }






    // -----------------------------------------------------------
    // rewardSum
    // -----------------------------------------------------------
    //
    // The atomic units blocks $from to $to pay together.
    //
    // Used by:
    //   - the Feature and Server suites — the mined totals
    //     they expect
    // -----------------------------------------------------------

    public static function rewardSum(int $from, int $to): int
    {
        $sum = 0;
        for ($height = $from; $height <= $to; $height++) {
            $sum += self::reward($height);
        }
        return $sum;
    }
}
