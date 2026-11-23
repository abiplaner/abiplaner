<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $examPeriodId;
    protected $students;
    protected $maxExams;

    public function __construct($examPeriodId)
    {
        $this->examPeriodId = $examPeriodId;
        
        // Lade Studenten mit allen Prüfungen
        $this->students = Student::where('exam_period_id', $examPeriodId)
            ->with([
                'exams.course.subject', 
                'exams.examiner',
                'exams.assignment.timeSlot', 
                'exams.assignment.examRoom'
            ])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(function ($student) {
                $allExams = collect();
                
                // ALLE Prüfungen aus student_exams
                foreach ($student->exams as $exam) {
                    $examData = [
                        'type' => $exam->exam_type ?? 'oral',
                        'subject' => $exam->course->subject->name ?? 'Unbekannt',
                        'course_code' => $exam->course->course_code ?? '',
                        'examiner' => $exam->examiner ? $exam->examiner->shortcode : '-',
                        'is_assigned' => $exam->isAssigned(),
                    ];
                    
                    if ($exam->exam_type === 'written') {
                        $examData['date_formatted'] = 'Nicht geplant';
                        $examData['time'] = '-';
                        $examData['room'] = '-';
                        $examData['seat'] = null;
                        $examData['sort_date'] = '9999-99-99 99:99';
                    } else {
                        $examData['date_formatted'] = $exam->assignment && $exam->assignment->timeSlot 
                            ? $exam->assignment->timeSlot->date->format('d.m.Y') 
                            : '-';
                        $examData['time'] = $exam->assignment && $exam->assignment->timeSlot 
                            ? substr($exam->assignment->timeSlot->exam_start_time, 0, 5)
                            : '-';
                        $examData['room'] = $exam->assignment && $exam->assignment->examRoom 
                            ? $exam->assignment->examRoom->room_number 
                            : '-';
                        $examData['seat'] = null;
                        $examData['sort_date'] = $exam->assignment && $exam->assignment->timeSlot 
                            ? $exam->assignment->timeSlot->date->format('Y-m-d') . ' ' . $exam->assignment->timeSlot->exam_start_time
                            : '9999-99-99 99:99';
                    }
                    
                    $allExams->push($examData);
                }
                
                $allExams = $allExams->sortBy('sort_date')->values();

                $student->all_exams = $allExams;
                return $student;
            });
        
        // Ermittle maximale Anzahl an Prüfungen
        $this->maxExams = $this->students->max(function($student) {
            return $student->all_exams->count();
        }) ?? 0;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): \Illuminate\Support\Collection
    {
        return $this->students;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $headings = ['Nr.', 'Nachname', 'Vorname'];
        
        for ($i = 1; $i <= $this->maxExams; $i++) {
            $headings[] = "Prüfung {$i} Art";
            $headings[] = "Prüfung {$i} Fach";
            $headings[] = "Prüfung {$i} Kurs";
            $headings[] = "Prüfung {$i} Prüfer";
            $headings[] = "Prüfung {$i} Datum";
            $headings[] = "Prüfung {$i} Uhrzeit";
            $headings[] = "Prüfung {$i} Raum";
            $headings[] = "Prüfung {$i} Platz";
        }
        
        return $headings;
    }

    /**
     * @param mixed $student
     * @return array
     */
    public function map($student): array
    {
        $row = [
            $student->external_id,
            $student->lastname,
            $student->firstname,
        ];
        
        for ($i = 0; $i < $this->maxExams; $i++) {
            $exam = $student->all_exams->get($i);
            
            if ($exam) {
                $row[] = $exam['type'] === 'oral' ? 'Mündlich' : 'Schriftlich';
                $row[] = $exam['subject'];
                $row[] = $exam['course_code'];
                $row[] = $exam['examiner'];
                $row[] = $exam['date_formatted'];
                $row[] = $exam['time'];
                $row[] = $exam['room'];
                $row[] = $exam['seat'] ?? '-';
            } else {
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
            }
        }
        
        return $row;
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        $widths = [
            'A' => 8,   // Nr.
            'B' => 20,  // Nachname
            'C' => 20,  // Vorname
        ];
        
        $columns = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
                    'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT'];
        
        $colIndex = 0;
        for ($i = 0; $i < $this->maxExams; $i++) {
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 10;  // Art
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 15;  // Fach
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 10;  // Kurs
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 10;  // Prüfer
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 12;  // Datum
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 10;  // Uhrzeit
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 10;  // Raum
            if (isset($columns[$colIndex])) $widths[$columns[$colIndex++]] = 8;   // Platz
        }
        
        return $widths;
    }
}
