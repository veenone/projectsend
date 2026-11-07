import { Cylinder, HardDrive, Info } from 'lucide-react';

import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export interface GroupStorageProps {
    can_assign: boolean;
    options: { id: number; name: string; local: boolean; location: string }[];
    hidden_names: string[];
    assigned_ids: number[];
    members_count: number;
}

/**
 * The group edit page's Storage tab body: the locations every member may
 * upload to, as a checklist. Read-only for an editor who may not change
 * the installation's settings.
 */
export function GroupStorageLocations({
    storage,
    value,
    onChange,
    error,
}: {
    storage: GroupStorageProps;
    value: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}) {
    const { t } = useTranslation();

    const toggle = (id: number, checked: boolean) => onChange(checked ? [...value, id] : value.filter((existing) => existing !== id));

    return (
        <section className="grid min-w-0 gap-3 rounded-lg border p-4" aria-labelledby="group-storage-locations">
            <div>
                <Label id="group-storage-locations" className="text-base">
                    {t('Storage locations')}
                </Label>
                <p className="text-muted-foreground text-sm">
                    {t(
                        "Members can upload to these, along with any personal locations they have. A member's personal first choice stays preselected.",
                    )}
                </p>
            </div>

            <ul className="grid min-w-0 gap-2">
                {storage.options.map((option) => {
                    const checked = value.includes(option.id);
                    return (
                        <li key={option.id}>
                            <label
                                className={`flex min-w-0 items-center gap-3 rounded-md border px-3 py-2 ${checked ? 'border-primary/40 bg-primary/5' : ''} ${storage.can_assign ? 'cursor-pointer' : ''}`}
                            >
                                <Checkbox
                                    checked={checked}
                                    disabled={!storage.can_assign}
                                    onCheckedChange={(state) => toggle(option.id, state === true)}
                                    aria-label={option.name}
                                />
                                <span className="text-muted-foreground hidden sm:block">
                                    {option.local ? <HardDrive className="size-4" /> : <Cylinder className="size-4" />}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block font-medium">{option.name}</span>
                                    <span className="text-muted-foreground block truncate font-mono text-[11px]">{option.location}</span>
                                </span>
                            </label>
                        </li>
                    );
                })}
            </ul>

            {storage.hidden_names.map((name) => (
                <p key={name} className="text-muted-foreground flex items-center gap-2 text-xs">
                    <Info className="size-3.5 shrink-0" />
                    {t(':name is hidden here because it no longer accepts uploads.', { name })}
                </p>
            ))}

            <p className="text-muted-foreground text-xs">
                {t('Anyone added to this group, including through requests and invitations, can upload here.')}
            </p>

            {!storage.can_assign && (
                <p className="text-muted-foreground text-xs">{t('Only someone who can change the installation’s settings can change these.')}</p>
            )}

            {error && <p className="text-destructive text-sm">{error}</p>}

            <p className="bg-muted text-muted-foreground rounded-md px-3 py-2 text-xs">
                {t('Affects new uploads by :count members. Files already uploaded stay where they are.', { count: storage.members_count })}
            </p>
        </section>
    );
}
