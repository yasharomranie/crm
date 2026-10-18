<?php

namespace App\Domain\Ticketing;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The box-office report as an Excel workbook, one sheet per section, built
 * from the very array the report page shows (TicketingReport::build()) so
 * the file can never disagree with the screen.
 */
class TicketingReportExport
{
    /**
     * @param  array<string, mixed>  $report
     * @return string path of a temporary .xlsx file (the caller deletes it)
     */
    public function render(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ticketing_report_');
        $writer = new Writer;
        $writer->openToFile($path);

        $sheets = $this->sheets($report);
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;

            $header = array_shift($rows);
            $writer->addRow(Row::fromValues($header, (new Style)->setFontBold()->setBackgroundColor('F3F4F6')));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, list<list<string|int>>> sheet name => rows, header row first
     */
    public function sheets(array $report): array
    {
        $sales = $report['sales'];

        return [
            'خلاصه' => [
                ['شاخص', 'مقدار'],
                ['از', $report['from']->toJalali('Y/m/d H:i')],
                ['تا', $report['to']->toJalali('Y/m/d H:i')],
                ['سفارش‌ها', $sales['orders']],
                ['بلیت‌های فروخته‌شده', $sales['tickets']],
                ['فروش ناخالص (تومان)', $sales['gross']],
                ['تخفیف (تومان)', $sales['discount']],
                ['فروش خالص (تومان)', $sales['net']],
            ],
            'محصول' => [['محصول', 'تعداد بلیت', 'فروش خالص (تومان)'], ...array_map(fn ($r) => [$r['name'], $r['tickets'], $r['net']], $sales['by_product'])],
            'صندوقدار' => [['صندوقدار', 'تعداد سفارش', 'فروش خالص (تومان)'], ...array_map(fn ($r) => [$r['name'], $r['orders'], $r['net']], $sales['by_cashier'])],
            'روش پرداخت' => [['روش', 'مبلغ (تومان)'], ...array_map(fn ($r) => [$r['method'], $r['amount']], $sales['by_method'])],
            'منبع فروش' => [['منبع', 'تعداد سفارش', 'فروش خالص (تومان)'], ...array_map(fn ($r) => [$r['source'], $r['orders'], $r['net']], $sales['by_source'])],
            'کد تخفیف' => [['کد', 'تعداد سفارش', 'تخفیف (تومان)', 'فروش پس از تخفیف (تومان)'], ...array_map(fn ($r) => [$r['code'], $r['orders'], $r['discount'], $r['net']], $report['discounts'])],
            'استفاده‌نشده' => [['محصول', 'تعداد بلیت', 'ارزش (تومان)'], ...array_map(fn ($r) => [$r['name'], $r['tickets'], $r['value']], $report['unused']['by_product'])],
            'رایگان' => [['دلیل', 'تعداد بلیت', 'ارزش اسمی (تومان)'], ...array_map(fn ($r) => [$r['reason'], $r['tickets'], $r['nominal_value']], $report['complimentary']['by_reason'])],
            'ورود ساعتی' => [['ساعت', 'تعداد عبور'], ...array_map(fn (int $hour, int $count) => [$hour, $count], array_keys($report['entry_by_hour']), array_values($report['entry_by_hour']))],
        ];
    }
}
