import { ref } from 'vue';
import { useStorage } from '@vueuse/core';

export type ScanRecord = {
    studentName: string;
    studentNumber: string;
    status: string;
    time: string;
    subjectName?: string;
};

const history = useStorage<ScanRecord[]>('scan-history-v1', [], sessionStorage);

export function useScanHistory() {
    function addScan(record: ScanRecord) {
        history.value = [record, ...history.value].slice(0, 50);
    }

    function clearHistory() {
        history.value = [];
    }

    return {
        history,
        addScan,
        clearHistory,
    };
}
