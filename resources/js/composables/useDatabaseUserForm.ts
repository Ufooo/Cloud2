import { generateSecurePassword } from '@/utils/password';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';

export interface DatabaseItem {
    id: string;
    name: string;
    status?: string;
}

interface UseDatabaseUserFormOptions {
    databases: Ref<DatabaseItem[]>;
    initialData?: {
        password?: string;
        databases?: string[];
        readonly?: boolean;
    };
}

export function useDatabaseUserForm(options: UseDatabaseUserFormOptions) {
    const { databases, initialData } = options;

    const form = useForm({
        password: initialData?.password ?? '',
        databases: initialData?.databases ?? ([] as string[]),
        readonly: initialData?.readonly ?? false,
    });

    const showPassword = ref(false);
    const databaseSearch = ref('');

    const installedDatabases = computed(() =>
        databases.value.filter((db) => db.status === 'installed'),
    );

    const filteredDatabases = computed(() => {
        if (!databaseSearch.value) {
            return installedDatabases.value;
        }
        return installedDatabases.value.filter((db) =>
            db.name.toLowerCase().includes(databaseSearch.value.toLowerCase()),
        );
    });

    function generatePassword() {
        form.password = generateSecurePassword();
        showPassword.value = true;
    }

    function toggleDatabase(databaseId: string) {
        const index = form.databases.indexOf(databaseId);
        if (index === -1) {
            form.databases.push(databaseId);
        } else {
            form.databases.splice(index, 1);
        }
    }

    function selectAllDatabases() {
        form.databases = installedDatabases.value.map((db) => db.id);
    }

    function resetForm(data?: {
        password?: string;
        databases?: string[];
        readonly?: boolean;
    }) {
        form.reset();
        form.databases = data?.databases ?? [];
        form.readonly = data?.readonly ?? false;
        form.password = data?.password ?? '';
        showPassword.value = false;
        databaseSearch.value = '';
    }

    return {
        form,
        showPassword,
        databaseSearch,
        filteredDatabases,
        installedDatabases,
        generatePassword,
        toggleDatabase,
        selectAllDatabases,
        resetForm,
    };
}
