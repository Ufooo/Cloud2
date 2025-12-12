<script setup lang="ts">
import {
    destroy,
    update,
} from '@/actions/Nip/Server/Http/Controllers/ServerController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/composables/useClipboard';
import { useConfirmation } from '@/composables/useConfirmation';
import ServerLayout from '@/layouts/ServerLayout.vue';
import { IdentityColor, type Server } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, Key, Trash2 } from 'lucide-vue-next';

interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    server: Server;
    timezones: SelectOption[];
    colors: SelectOption[];
}

const props = defineProps<Props>();

const { confirmInput } = useConfirmation();
const { copy } = useClipboard();

const form = useForm({
    name: props.server.name,
    ssh_port: props.server.sshPort,
    ip_address: props.server.ipAddress || '',
    private_ip_address: props.server.privateIpAddress || '',
    timezone: props.server.timezone,
    avatar_color: props.server.avatarColor,
});

const colorMap: Record<IdentityColor, string> = {
    [IdentityColor.Blue]: 'bg-blue-500',
    [IdentityColor.Green]: 'bg-green-500',
    [IdentityColor.Orange]: 'bg-orange-500',
    [IdentityColor.Purple]: 'bg-purple-500',
    [IdentityColor.Red]: 'bg-red-500',
    [IdentityColor.Yellow]: 'bg-yellow-500',
    [IdentityColor.Cyan]: 'bg-cyan-500',
    [IdentityColor.Gray]: 'bg-gray-500',
};

function submitForm() {
    form.transform((data) => ({
        ...data,
        ip_address: data.ip_address || null,
        private_ip_address: data.private_ip_address || null,
    })).patch(update.url(props.server));
}

async function handleDelete() {
    const confirmed = await confirmInput({
        title: 'Delete server',
        description: `Type "${props.server.name}" to confirm permanently deleting this server. This action cannot be undone.`,
        value: props.server.name,
    });

    if (confirmed) {
        router.delete(destroy.url(props.server));
    }
}

// Mock public key for now - this would come from the server
const serverPublicKey =
    'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCrrAenhM5OpthlwzbjZ9KccmRSs5nJKWW8Ui9MloZS/GnsxGKlufS4nlUdWPjMtNUvo2I1orvCA3rKDRH28ZEIq2P/watFKhW5caVDvKAylCC+TPtg0Hv6b20Ka1zlbzUMU2kOMZBuw885Z8306ENxZaMW5kAF+yGusj2d5QANgDSpK16T3br02+Wvq2wBi4dR3Kp/Rj71Dt9CeT9EWmqCD3REaAyQ4pK/e40X3TIl8Wye/9sS9nye1Bu8ogUhVhsOQ6O4L3RdB1dV/OmWTgmi7+plh9mbx2Rzvm31H2WH8Pr190fcoUuqe8bMYpDpTNzuCr+cWJkcAnzgSjSYwKL/ root@server';
</script>

<template>
    <Head :title="`Settings - ${server.name}`" />

    <ServerLayout :server="server">
        <div class="space-y-6">
            <!-- Settings Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Settings</CardTitle>
                    <CardDescription>
                        Manage and configure your server's basic settings.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <!-- Name -->
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="my-server"
                            />
                            <p class="text-sm text-muted-foreground">
                                The name used to identify your server.
                            </p>
                            <InputError :message="form.errors.name" />
                        </div>

                        <!-- Size (read-only) -->
                        <div class="space-y-2">
                            <Label>Size</Label>
                            <p class="text-sm text-muted-foreground">
                                The amount of resources allocated to your
                                server.
                            </p>
                            <div
                                class="rounded-md border bg-muted/50 px-3 py-2 text-sm"
                            >
                                {{ server.displayableType || 'Unknown' }}
                            </div>
                        </div>

                        <!-- SSH Port -->
                        <div class="space-y-2">
                            <Label for="ssh_port">SSH port</Label>
                            <Input
                                id="ssh_port"
                                v-model="form.ssh_port"
                                type="text"
                                placeholder="22"
                            />
                            <p class="text-sm text-muted-foreground">
                                The port that will be used to connect to your
                                server via SSH.
                            </p>
                            <InputError :message="form.errors.ssh_port" />
                        </div>

                        <!-- IP Address -->
                        <div class="space-y-2">
                            <Label for="ip_address">IP address</Label>
                            <Input
                                id="ip_address"
                                v-model="form.ip_address"
                                type="text"
                                placeholder="192.168.1.1"
                            />
                            <p class="text-sm text-muted-foreground">
                                The public IP address that will be used to
                                connect to your server via SSH.
                            </p>
                            <InputError :message="form.errors.ip_address" />
                        </div>

                        <!-- Private IP Address -->
                        <div class="space-y-2">
                            <Label for="private_ip_address"
                                >Private IP address</Label
                            >
                            <Input
                                id="private_ip_address"
                                v-model="form.private_ip_address"
                                type="text"
                                placeholder="10.0.0.1"
                            />
                            <p class="text-sm text-muted-foreground">
                                The internal IP address used for server
                                communication within a private network.
                            </p>
                            <InputError
                                :message="form.errors.private_ip_address"
                            />
                        </div>

                        <!-- Timezone -->
                        <div class="space-y-2">
                            <Label for="timezone">Timezone</Label>
                            <Select v-model="form.timezone">
                                <SelectTrigger id="timezone">
                                    <SelectValue
                                        placeholder="Select timezone"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tz in props.timezones"
                                        :key="tz.value"
                                        :value="tz.value"
                                    >
                                        {{ tz.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-sm text-muted-foreground">
                                The timezone that your server is in. This is
                                used for scheduling tasks and other
                                time-sensitive operations.
                            </p>
                            <InputError :message="form.errors.timezone" />
                        </div>

                        <!-- Color -->
                        <div class="space-y-2">
                            <Label>Color</Label>
                            <p class="text-sm text-muted-foreground">
                                Select a color to identify your server.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in props.colors"
                                    :key="color.value"
                                    type="button"
                                    class="size-8 rounded-md ring-offset-background transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    :class="[
                                        colorMap[
                                            color.value as IdentityColor
                                        ] || 'bg-gray-500',
                                        form.avatar_color === color.value
                                            ? 'ring-2 ring-ring ring-offset-2'
                                            : 'hover:opacity-80',
                                    ]"
                                    :title="color.label"
                                    @click="
                                        form.avatar_color =
                                            color.value as IdentityColor
                                    "
                                />
                            </div>
                            <InputError :message="form.errors.avatar_color" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : 'Save Changes'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Keys Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Key class="size-5" />
                        Keys
                    </CardTitle>
                    <CardDescription>
                        Your server's public SSH keys.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label>Server's public key</Label>
                        <p class="text-sm text-muted-foreground">
                            Typically, this key will automatically be added to
                            GitHub, GitLab. However, if you need to add it to a
                            source control service manually, you may copy it
                            from here.
                        </p>
                        <div class="relative">
                            <div
                                class="rounded-md border bg-muted/50 p-3 pr-12 font-mono text-xs break-all"
                            >
                                {{ serverPublicKey }}
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute top-2 right-2"
                                @click="copy(serverPublicKey)"
                            >
                                <Copy class="size-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Danger Zone -->
            <Card class="border-destructive/50">
                <CardHeader>
                    <CardTitle class="text-destructive">Danger</CardTitle>
                    <CardDescription>
                        Destructive settings that cannot be undone.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="font-medium">Delete server</p>
                            <p class="text-sm text-muted-foreground">
                                Deleting your server will permanently delete all
                                of its data. This action cannot be undone.
                            </p>
                        </div>
                        <Button
                            v-if="server.can?.delete"
                            variant="destructive"
                            @click="handleDelete"
                        >
                            <Trash2 class="mr-2 size-4" />
                            Delete server
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </ServerLayout>
</template>
