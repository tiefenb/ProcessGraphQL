<?php

namespace ProcessWire\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;
use ProcessWire\WireArray;
use ProcessWire\WireData;

class EmptyImage extends WireData {

  public function getVariations()
  {
    return new WireArray();
  }

}

class ImageType {

  public const NAME = 'Image';

  public const DESCRIPTION = 'ProcessWire PageImage.';

  public static function type(Context $context): ObjectType
  {
    return $context->types->type(self::NAME, fn() => new ObjectType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'fields' => fn() => [
        ...self::getLegalBuiltInFields($context),
        ...FileType::getLegalBuiltInFields($context),
      ],
    ]));
  }

  public static function getBuiltInFields(Context $context): array
  {
    $type = self::type($context);
    return [
      [
        'name' => 'width',
        'type' => Type::int(),
        'description' => 'The width of the image.',
        'resolve' => fn($value) => (int) $value->width,
      ],
      [
        'name' => 'height',
        'type' => Type::int(),
        'description' => 'The height of the image.',
        'resolve' => fn($value) => (int) $value->height,
      ],
      [
        'name' => 'variations',
        'type' => Type::listOf($type),
        'description' => 'Returns all size variations of the image.',
        'resolve' => fn($value) => $value->getVariations(),
      ],
      [
        'name' => 'size',
        'type' => $type,
        'description' => 'Create a thumbnail of the PageImage with the desired size.',
        'args' => [
          [
            'name' => 'width',
            'type' => Type::int(),
            'description' => 'Target width of the new image',
          ],
          [
            'name' => 'height',
            'type' => Type::int(),
            'description' => 'Target height of the new image',
          ],
        ],
        'resolve' => function ($value, array $args, Context $context) {
          $width = $args['width'] ?? null;
          $height = $args['height'] ?? null;

          // if there neither width nor heigth is given then we return empty image
          if (!$width && !$height) {
            return new EmptyImage();
          }

          // we create the image if user have rights for it
          if ($context->permissions->canEditField($value->field, $value->page->template)) {
            return $value->size($width, $height);
          }

          // if user has no rights to create the image then she
          // might be asking for variation already created
          return array_find(
            $value->getVariations()->getArray(),
            fn($variation) => (!$width || $variation->width === $width)
              && (!$height || $variation->height === $height)
          ) ?? new EmptyImage();
        }
      ]
    ];
  }

  public static function getLegalBuiltInFields(Context $context): array
  {
    $legal = array_flip($context->module->legalPageImageFields);
    return array_filter(
      self::getBuiltInFields($context),
      fn($field) => isset($legal[$field['name']])
    );
  }
}
