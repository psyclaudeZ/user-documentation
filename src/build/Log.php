<?hh // strict

namespace HHVM\UserDocumentation;

enum Color: string {
  RESET = '[0m';
  BLACK = '[30m';
  RED = '[31m';
  GREEN = '[32m';
  YELLOW = '[33m';
  PURPLE = '[35m';
  BLUE = '[34m';
  CYAN = '[36m';
  WHITE = '[37m';
}

class Log {
  const int NONE =    0;
  const int ERROR =   1;
  const int WARN =    3;
  const int INFO =    7;
  const int VERBOSE = 15;

  public static int $loglevel = self::VERBOSE;

  private static \ImmMap<int, Color> $color = ImmMap {
    self::ERROR => Color::RED,
    self::WARN => Color::YELLOW,
    self::INFO => Color::GREEN,
    self::VERBOSE => Color::RESET,
  };

  public static function e(string $msg): void {
    self::print(self::ERROR, $msg);
  }

  public static function w(string $msg): void {
    self::print(self::WARN, $msg);
  }

  public static function i(string $msg): void {
    self::print(self::INFO, $msg);
  }

  public static function v(string $msg): void {
    self::print(self::VERBOSE, $msg);
  }

  private static function print(int $loglevel, string $msg): void {
    if (self::$loglevel & $loglevel) {
      $color = self::$color[$loglevel];
      if (strpos($msg, "\n") === 0) {
        $msg = strftime("\n%Y-%m-%d %H:%M:%S] ").ltrim($msg);
      }
      print(self::color($color, $msg));
    }
  }

  private static function color(Color $color, string $msg): string {
    return chr(27) . $color . $msg . chr(27) . Color::RESET;
  }
}
