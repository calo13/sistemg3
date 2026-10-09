<?php

namespace App\Services;

class PagingFlowService
{
    /** @return list<array{id:string,label:string,detail:string}> */
    public function steps(bool $hit): array
    {
        $steps = [
            [
                'id' => 'cpu-request',
                'label' => 'La CPU solicita una página',
                'detail' => 'La solicitud identifica el proceso y el número de página que se quiere consultar.',
            ],
            [
                'id' => 'page-table',
                'label' => 'Consultar la tabla de páginas',
                'detail' => 'La entrada de la página indica si está presente en RAM y qué marco tiene asignado.',
            ],
        ];

        if ($hit) {
            return array_merge($steps, [
                [
                    'id' => 'page-present',
                    'label' => 'Página presente: PAGE HIT',
                    'detail' => 'La página ya tiene un marco en RAM. No hace falta cargarla desde almacenamiento secundario.',
                ],
                [
                    'id' => 'access-completed',
                    'label' => 'Acceso correcto',
                    'detail' => 'El marco de la tabla permite localizar la página en la memoria física simulada.',
                ],
            ]);
        }

        return array_merge($steps, [
            [
                'id' => 'page-absent',
                'label' => 'Página ausente de RAM',
                'detail' => 'La página no tiene un marco asignado y se encuentra en almacenamiento secundario simulado.',
            ],
            [
                'id' => 'page-fault',
                'label' => 'PAGE FAULT',
                'detail' => 'El acceso requiere traer la página a RAM antes de poder continuar.',
            ],
            [
                'id' => 'find-frame',
                'label' => 'Buscar un marco libre o aplicar FIFO',
                'detail' => 'Se utiliza el primer marco libre. Si RAM está llena, FIFO selecciona la página que lleva más tiempo cargada para enviarla a almacenamiento secundario.',
            ],
            [
                'id' => 'load-page',
                'label' => 'Cargar desde almacenamiento secundario',
                'detail' => 'La página solicitada pasa al marco elegido. Si hubo reemplazo, la página retirada ocupa espacio en almacenamiento secundario simulado.',
            ],
            [
                'id' => 'update-table',
                'label' => 'Actualizar la tabla de páginas',
                'detail' => 'La entrada de la página muestra su marco y el estado presente. La página retirada, si la hubo, queda sin marco.',
            ],
            [
                'id' => 'retry-access',
                'label' => 'Reintentar el acceso: acceso correcto',
                'detail' => 'Con la página en RAM, la CPU puede completar el acceso usando el marco de la tabla.',
            ],
        ]);
    }
}
