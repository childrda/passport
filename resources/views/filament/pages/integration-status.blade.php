<x-filament-panels::page>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left dark:border-white/10 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Setting</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Status</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Ready</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->getStatusRows() as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['value'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['ok'])
                                    <span class="inline-flex rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                        OK
                                    </span>
                                @else
                                    <span class="inline-flex rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30">
                                        Check
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            OAuth secrets, service-account JSON contents, and other deployment credentials are managed only via
            <code class="text-xs">.env</code> / server configuration and cannot be edited in this panel.
        </p>
    </div>
</x-filament-panels::page>
