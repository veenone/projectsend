import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { ReactNode, useState } from 'react';

/**
 * Visit callbacks a dialog with an `errorField` hands to onConfirm, for the
 * caller to pass on to its Inertia request. Empty otherwise.
 */
export interface ConfirmVisit {
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
}

/**
 * Turns a confirm click into the visit callbacks above: the dialog stays
 * open until the server answers, closes on success, and shows the error on
 * `errorField` inside itself when that is what came back. Any other error
 * closes it, as the dialog did before it waited.
 */
export function useConfirmVisit(errorField: string, close: () => void) {
    const [error, setError] = useState<string>();
    const [processing, setProcessing] = useState(false);

    const visit = (): ConfirmVisit => {
        setError(undefined);
        setProcessing(true);

        return {
            onSuccess: close,
            onError: (errors) => (errors[errorField] ? setError(errors[errorField]) : close()),
            onFinish: () => setProcessing(false),
        };
    };

    return { error, setError, processing, visit };
}

interface ConfirmDialogProps {
    /** The button that opens the dialog. */
    trigger: ReactNode;
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    /** Keep the dialog open until the request answers and show the server's error on this field in it. */
    errorField?: string;
    onConfirm: (visit: ConfirmVisit) => void;
}

/**
 * App-wide rule: destructive actions ALWAYS require confirmation.
 * Wrap the action button in this dialog; the action only runs after an
 * explicit confirm click.
 */
export function ConfirmDialog({ trigger, title, description, confirmLabel, destructive = true, errorField, onConfirm }: ConfirmDialogProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { error, setError, processing, visit } = useConfirmVisit(errorField ?? '', () => setOpen(false));

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) setError(undefined);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <InputError message={error} />
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline">{t('Cancel')}</Button>
                    </DialogClose>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={processing}
                        onClick={() => {
                            if (errorField === undefined) {
                                setOpen(false);
                                onConfirm({});

                                return;
                            }

                            onConfirm(visit());
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
