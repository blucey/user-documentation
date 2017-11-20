<?hh // strict
/*
 *  Copyright (c) 2004-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

namespace HHVM\UserDocumentation;

use phpDocumentor\Reflection\DocBlock\Tag\ReturnTag;
use phpDocumentor\Reflection\DocBlock\Tag\ParamTag;

use namespace HH\Lib\{C, Str, Vec};

class Stringify {

  public static function typehint(TypehintDocumentation $typehint): string {
    if (!Str\starts_with_ci($typehint['typetext'], $typehint['typename'])) {
      return $typehint['typetext'];
    }

    $s = $typehint['nullable'] ? '?' : '';
    $s .= $typehint['typename'];

    if ($typehint['genericTypes']) {
      $s .= '<';
      $s .= implode(
        ',',
        array_map(
          $typehint ==> Stringify::typehint(/* UNSAFE_EXPR */ $typehint),
          $typehint['genericTypes'],
        ),
      );
      $s .= '>';
    }
    return $s;
  }

  public static function parameter(
    ParameterDocumentation $param,
    ?ReturnTag $tag,
  ): string {
    $name = $param['name'];

    $s = '';
    $types = $tag?->getTypes();
    if ($types !== null && $types !== []) {
      $s .= implode('|',$types).' ';
    } else {
      $th = $param['typehint'];
      if ($th !== null) {
        $s .= Stringify::typehint($th).' ';
      }
    }

    if ($param['isVariadic']) {
      $s .= '...';
    }
    if ($param['isPassedByReference']) {
      $s .= '&';
    }
    $s .= '$'.$param['name'];

    if ($param['isOptional']) {
      $default = $param['default'];
      // TODO: It looked like @fredemmott's ScannedParameter class handled
      // normalizing an empty string to "\"\"", but it gets lost somewhere
      // before here. This allows default "" parameters to show up though. It
      // just may be in a non-optimal place in the pipeline.
      if ($default === "") {
        $default = "\"\"";
      }
      invariant(
        $default !== null,
        'optional parameter without a default',
      );
      $s .= ' = '.$default;
    }
    return $s;
  }

  public static function signature(
    FunctionDocumentation $func,
    StringifyFormat $format = StringifyFormat::MULTI_LINE,
  ): string {
    $ret = '';

    $visibility = $func['visibility'] ?? null;
    if ($visibility !== null) {
      $ret .= $visibility.' ';
    }
    if ($func['static'] ?? false === true) {
      $ret .= 'static ';
    }
    $name = $func['name']
      |> Str\split($$, '\\')
      |> C\lastx($$);
    $ret .= 'function '.$name;

    $ret .= self::generics($func['generics']);

    $ret .= Stringify::parameters($func, $format);

    $return_type = $func['returnType'];
    if ($return_type !== null) {
      $ret .= ': '.Stringify::typehint($return_type);
    }

    return $ret;
  }

  public static function interfaceSignature(
    ClassDocumentation $iface,
  ): string {
    $ret = '';
    $ns = $iface['namespace'];
    if ($ns !== '') {
      $ret .= 'namespace ' . $ns . " { ";
    }
    $ret .= $iface['type'] . ' ' . $iface['shortName'] . ' ';
    $parent = $iface['parent'];
    if ($parent !== null) {
      $ret .= 'extends ' . $parent['typename'] . ' ';
    }
    $implInterfaces = $iface['interfaces'];
    if (count($implInterfaces) > 0) {
      $ret .= 'implements ';
      foreach ($implInterfaces as $implInterface) {
        $ret .= $implInterface['typename'] . ', ';
      }
      $ret = substr($ret, 0, -2); // remove trailing ', ''
    }
    $ret .= ' {...}';
    if ($ns !== '') {
      $ret .= ' }';
    }
    $ret .= "\n";
    return $ret;
  }

  public static function parameters(
    FunctionDocumentation $func,
    StringifyFormat $format = StringifyFormat::MULTI_LINE,
  ): string {
    $tags = DocblockTagReader::newFromString($func['docComment'])
      ->getParamTags();

    $params = array_map(
      $param ==> Stringify::parameter($param, idx($tags, $param['name'])),
      $func['parameters'],
    );

    if (!$params) {
      return '()';
    }

    switch($format) {
      case StringifyFormat::MULTI_LINE:
        return
          "(\n".
          implode("\n", array_map($x ==> '  '.$x.',', $params)).
          "\n)";
      case StringifyFormat::ONE_LINE:
        return '('.implode(', ', $params).')';
    }
  }

  public static function generics(array<GenericDocumentation> $generics): string {
    if (count($generics) === 0) {
      return '';
    }

    $generics = Vec\map(
      $generics,
      $generic ==> {
        $name = $generic['name'];
        $constraint = $generic['constraint'] ?? '';
        if ($constraint !== '') {
          return $name.' '.$constraint;
        }
        return $name;
      },
    );
    return '<'.Str\join($generics, ', ').'>';
  }
}
