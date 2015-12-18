<?hh

namespace Hack\UserDocumentation\Async\Generators\Examples\Raise;

require __DIR__ . "/../../../../vendor/hh_autoload.php";

const HALF_SECOND = 500000; // microseconds

async function get_name_string(int $id): Awaitable<string> {
  // simulate fetch to database where we would actually use $id
  await \HH\Asio\usleep(HALF_SECOND);
  return str_shuffle("ABCDEFG");
}

async function generate(): AsyncGenerator<int, string, int> {
  $id = yield 0 => ''; // initialize $id
  // $id is a ?int; you can pass null to send()
  while ($id !== null) {
    $name = "";
    try {
      $name = await get_name_string($id);
      $id = yield $id => $name; // key/string pair
    } catch (\Exception $ex) {
      var_dump($ex->getMessage());
      $id = yield 0 => '';
    }
  }
}

async function associate_ids_to_names(
  Vector<int> $ids
): Awaitable<void> {
  $async_generator = generate();
  // You have to call next() before you send. So this is the priming step and
  // you will get the initialization result from generate()
  $result = await $async_generator->next();
  var_dump($result);

  foreach ($ids as $id) {
    if ($id === 3) {
      $result = await $async_generator->raise(
        new \Exception("Id of 3 is bad!")
      );
    } else {
      $result = await $async_generator->send($id);
    }
    var_dump($result);
  }
}

function run(): void {
  $ids = Vector {1, 2, 3, 4};
  \HH\Asio\join(associate_ids_to_names($ids));
}

run();

