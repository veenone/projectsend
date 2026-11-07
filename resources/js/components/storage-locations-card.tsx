import { ChevronDown, ChevronUp, Cylinder, HardDrive, Plus, Upload, X } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

export interface StorageLocationOption {
    id: number;
    name: string;
    local: boolean;
    location: string;
    accepts_uploads: boolean;
    is_default: boolean;
    /** Whether the upload screen would offer it: accepts uploads and is set up. */
    usable: boolean;
}

export interface StorageProps {
    can_assign: boolean;
    options: StorageLocationOption[];
    assigned_ids: number[];
    files_count: number;
    /** Clients only: what their groups give that the upload screen offers. */
    from_groups?: { id: number; name: string; groups: string[] }[];
    /** Clients only: personal locations a group gives as well. */
    also_in_groups?: string[];
}

/**
 * The client and staff edit pages' "Storage locations" card: the places this
 * account's uploads may go, the first preferred. Reordered with the arrows
 * (no drag, so it works the same by keyboard and on a phone). Read-only
 * for an editor who may not change the installation's settings. A client's
 * card also lists, read-only, what their groups add.
 */
export function StorageLocationsCard({
    storage,
    value,
    onChange,
    error,
}: {
    storage: StorageProps;
    value: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}) {
    const { t } = useTranslation();
    const byId = new Map(storage.options.map((option) => [option.id, option]));
    const assigned = value.map((id) => byId.get(id)).filter((option): option is StorageLocationOption => option !== undefined);
    const available = storage.options.filter((option) => !value.includes(option.id));
    const [adding, setAdding] = useState('');
    const defaultLocation = storage.options.find((option) => option.is_default);
    const fromGroups = storage.from_groups ?? [];
    const offered = [...assigned.filter((option) => option.usable).map((option) => option.name), ...fromGroups.map((entry) => entry.name)];

    const move = (index: number, by: number) => {
        const next = [...value];
        const [moved] = next.splice(index, 1);
        next.splice(index + by, 0, moved);
        onChange(next);
    };

    const add = () => {
        const id = Number(adding);
        if (id > 0) onChange([...value, id]);
        setAdding('');
    };

    return (
        <section className="grid min-w-0 gap-3 rounded-lg border p-4" aria-labelledby="client-storage-locations">
            <div>
                <Label id="client-storage-locations" className="text-base">
                    {t('Storage locations')}
                </Label>
                <p className="text-muted-foreground text-sm">
                    {t('Their uploads go to the first location unless they choose another on the upload screen.')}
                </p>
            </div>

            {assigned.length === 0 ? (
                <p className="text-muted-foreground rounded-md border border-dashed px-3 py-4 text-center text-sm">
                    {t('No locations. Their uploads use the default, :name.', { name: defaultLocation?.name ?? '' })}
                </p>
            ) : (
                <ol className="grid min-w-0 gap-2">
                    {assigned.map((option, index) => (
                        <li
                            key={option.id}
                            className={`flex min-w-0 items-center gap-3 rounded-md border px-3 py-2 ${index === 0 ? 'border-primary/40 bg-primary/5' : ''}`}
                        >
                            <span
                                className={`flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${index === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}
                                aria-label={t('Priority :number', { number: index + 1 })}
                            >
                                {index + 1}
                            </span>
                            <span className="text-muted-foreground hidden sm:block">
                                {option.local ? <HardDrive className="size-4" /> : <Cylinder className="size-4" />}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center gap-1.5 font-medium">
                                    {option.name}
                                    {index === 0 && (
                                        <Badge variant="secondary" className="hidden sm:inline-flex">
                                            {t('Preselected')}
                                        </Badge>
                                    )}
                                    {!option.accepts_uploads && <Badge variant="outline">{t('Existing files only')}</Badge>}
                                </span>
                                <span className="text-muted-foreground block truncate font-mono text-[11px]">{option.location}</span>
                            </span>
                            {storage.can_assign && (
                                <span className="flex shrink-0 gap-0.5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className={`size-8 ${index === 0 ? 'invisible' : ''}`}
                                        onClick={() => move(index, -1)}
                                        aria-label={t('Move :name up', { name: option.name })}
                                    >
                                        <ChevronUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className={`size-8 ${index === assigned.length - 1 ? 'invisible' : ''}`}
                                        onClick={() => move(index, 1)}
                                        aria-label={t('Move :name down', { name: option.name })}
                                    >
                                        <ChevronDown className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-destructive hover:text-destructive size-8"
                                        onClick={() => onChange(value.filter((id) => id !== option.id))}
                                        aria-label={t('Remove :name', { name: option.name })}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </span>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {storage.can_assign && available.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    <Select value={adding} onValueChange={setAdding}>
                        <SelectTrigger className="w-full sm:w-72" aria-label={t('Location to add')}>
                            <SelectValue placeholder={t('Choose a location')} />
                        </SelectTrigger>
                        <SelectContent>
                            {available.map((option) => (
                                <SelectItem key={option.id} value={String(option.id)}>
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" onClick={add} disabled={adding === ''}>
                        <Plus className="size-4" />
                        {t('Add')}
                    </Button>
                </div>
            )}

            {!storage.can_assign && (
                <p className="text-muted-foreground text-xs">{t('Only someone who can change the installation’s settings can change these.')}</p>
            )}

            {error && <p className="text-destructive text-sm">{error}</p>}

            {fromGroups.length > 0 && (
                <div className="grid min-w-0 gap-2">
                    <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                        {t('From groups')} <span className="font-normal tracking-normal normal-case">{t('Read-only. Change it on the group.')}</span>
                    </p>
                    <ul className="grid min-w-0 gap-2">
                        {fromGroups.map((entry) => (
                            <li key={entry.id} className="bg-muted/50 flex min-w-0 flex-wrap items-center gap-2 rounded-md border px-3 py-2">
                                <span className="text-muted-foreground">
                                    {byId.get(entry.id)?.local ? <HardDrive className="size-4" /> : <Cylinder className="size-4" />}
                                </span>
                                <span className="font-medium">{entry.name}</span>
                                <span className="text-muted-foreground text-xs">{t('via')}</span>
                                {entry.groups.map((group) => (
                                    <Badge key={group} variant="outline">
                                        {group}
                                    </Badge>
                                ))}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {(storage.also_in_groups ?? []).map((name) => (
                <p key={name} className="text-muted-foreground text-xs">
                    {t(':name also comes from a group; it is listed once, above.', { name })}
                </p>
            ))}

            {offered.length > 0 && (
                <p className="border-primary/40 flex items-start gap-2 rounded-md border px-3 py-2 text-sm">
                    <Upload className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                    <span>
                        {t('Upload screen offers locations (:count): :list.', {
                            count: offered.length,
                            list: offered.map((name, index) => (index === 0 ? `${name} ${t('(preselected)')}` : name)).join(', '),
                        })}
                    </span>
                </p>
            )}

            <p className="bg-muted text-muted-foreground rounded-md px-3 py-2 text-xs">
                {t('Changing this affects new uploads only. Files already uploaded (:count) stay where they are.', { count: storage.files_count })}
            </p>
        </section>
    );
}
