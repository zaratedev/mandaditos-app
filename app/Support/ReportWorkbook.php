<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The reports page as a spreadsheet: one sheet per table, the numbers written as
 * numbers so they can be summed on arrival, and the period spelled out on the
 * first sheet so a file that outlives this conversation still says what it covers.
 */
final readonly class ReportWorkbook
{
    public function __construct(private PeriodReport $report) {}

    public function writeTo(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $this->summary($writer);
        $this->perDay($writer);
        $this->clients($writer);
        $this->couriers($writer);
        $this->methods($writer);
        $this->products($writer);

        $writer->close();
    }

    /**
     * The name the file arrives with: the range it covers, so a folder full of
     * these is still readable a year from now.
     */
    public function filename(): string
    {
        return sprintf(
            'reportes-%s-a-%s.xlsx',
            $this->report->from()->format('Y-m-d'),
            $this->report->to()->format('Y-m-d'),
        );
    }

    private function summary(Writer $writer): void
    {
        $collected = $this->report->collected();
        $operations = $this->report->operations();
        $receivable = $this->report->receivable();
        $delivery = $this->report->deliveryTimes();

        $writer->getCurrentSheet()->setName('Resumen');

        $this->write($writer, [
            ['Reporte del', $this->date($this->report->from()), 'al', $this->date($this->report->to())],
            [],
            ['Dinero cobrado en el periodo', ''],
            ['Comisiones cobradas', $collected['commission']],
            ['Dinero movido (incluye el gasto del cliente)', $collected['moved']],
            ['Efectivo', $collected['cash']],
            ['Transferencia', $collected['transfer']],
            ['Pagos registrados', $collected['orders']],
            ['Ticket promedio', $collected['ticket']],
            ['Comisión promedio', $collected['fee']],
            [],
            ['Pedidos creados en el periodo', ''],
            ['Pedidos', $operations['orders']],
            ['Entregados', $operations['delivered']],
            ['Cancelados', $operations['cancelled']],
            ['Pedidos por día', $operations['perDay']],
            [],
            ['Por cobrar (a hoy, sin importar el periodo)', ''],
            ['Entregas sin pago', $receivable['orders']],
            ['Monto por cobrar', $receivable['amount']],
            ['Comisión dentro de ese monto', $receivable['commission']],
            ['Entrega sin pago más vieja', $receivable['oldest'] === null ? 'Ninguna' : $this->date($receivable['oldest'])],
            [],
            ['Tiempos de entrega', ''],
            ['Entregas medidas', $delivery['orders']],
            ['Promedio (minutos)', $delivery['average'] ?? 'Sin datos'],
            ['La más lenta (minutos)', $delivery['slowest'] ?? 'Sin datos'],
        ]);
    }

    private function perDay(Writer $writer): void
    {
        $this->sheet($writer, 'Por día', ['Fecha', 'Pedidos', 'Comisiones']);

        $this->write($writer, array_map(fn (array $row): array => [
            $this->date($row['date']),
            $row['orders'],
            $row['revenue'],
        ], $this->report->perDay()));
    }

    private function clients(Writer $writer): void
    {
        $this->sheet($writer, 'Clientes', ['Cliente', 'Pedidos', 'Comisiones', 'Dinero movido', 'Último pedido']);

        $this->write($writer, array_map(fn (array $row): array => [
            $row['client'],
            $row['orders'],
            $row['commission'],
            $row['moved'],
            $this->date($row['last']),
        ], $this->report->perClient()));
    }

    private function couriers(Writer $writer): void
    {
        $this->sheet($writer, 'Repartidores', [
            'Repartidor', 'Pedidos', 'Entregados', 'Tiempo promedio (minutos)', 'Comisiones', 'Dinero movido',
        ]);

        $this->write($writer, array_map(fn (array $row): array => [
            $row['courier'],
            $row['orders'],
            $row['delivered'],
            $row['average'] ?? 'Sin datos',
            $row['commission'],
            $row['moved'],
        ], $this->report->perCourier()));
    }

    private function methods(Writer $writer): void
    {
        $this->sheet($writer, 'Métodos de pago', ['Método', 'Pedidos', 'Total']);

        $this->write($writer, array_map(fn (array $row): array => [
            $row['method'],
            $row['orders'],
            $row['amount'],
        ], $this->report->perMethod()));
    }

    private function products(Writer $writer): void
    {
        $this->sheet($writer, 'Productos', ['Producto', 'Cantidad', 'Veces pedido', 'Total gastado']);

        $this->write($writer, array_map(fn (array $row): array => [
            $row['name'],
            $row['quantity'],
            $row['orders'],
            $row['spent'],
        ], $this->report->topProducts()));
    }

    /**
     * @param  list<string>  $headings
     */
    private function sheet(Writer $writer, string $name, array $headings): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName($name);

        $this->write($writer, [$headings]);
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function write(Writer $writer, array $rows): void
    {
        foreach ($rows as $cells) {
            $writer->addRow(Row::fromValues($cells));
        }
    }

    /**
     * Dates go out the way the app writes them everywhere else, DD/MM/YYYY.
     */
    private function date(CarbonInterface|string $value): string
    {
        $date = is_string($value) ? CarbonImmutable::parse($value) : $value;

        return $date->format('d/m/Y');
    }
}
