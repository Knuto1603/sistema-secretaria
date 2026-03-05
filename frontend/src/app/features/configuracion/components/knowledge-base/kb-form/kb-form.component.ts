import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin, of, switchMap } from 'rxjs';
import { KnowledgeBaseService } from '../../../../chatbot/services/knowledge-base.service';
import { KbArticle, KbDocument } from '../../../../chatbot/models/chat.models';

@Component({
  selector: 'app-kb-form',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './kb-form.component.html',
})
export class KbFormComponent implements OnInit {
  private kbSvc = inject(KnowledgeBaseService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  isEdit = false;
  articleId = '';
  loading = signal(false);
  saving = signal(false);

  form = {
    tipo: 'proceso' as KbArticle['tipo'],
    titulo: '',
    contenido: '',
    categoria: '',
    tags: [] as string[],
    activo: true,
    orden: 0,
  };

  tagInput = '';
  tipos: KbArticle['tipo'][] = ['proceso', 'faq', 'norma', 'requisito', 'resolucion'];

  // ── Documentos adjuntos ──────────────────────────────────────────────────
  attachedDocs = signal<KbDocument[]>([]);
  availableDocs = signal<KbDocument[]>([]);
  loadingDocs = signal(false);
  docSearch = '';

  get filteredAvailable(): KbDocument[] {
    const term = this.docSearch.toLowerCase();
    const attachedIds = new Set(this.attachedDocs().map(d => d.id));
    return this.availableDocs()
      .filter(d => !attachedIds.has(d.id))
      .filter(d => !term || d.titulo.toLowerCase().includes(term) || d.original_filename.toLowerCase().includes(term));
  }

  ngOnInit(): void {
    this.articleId = this.route.snapshot.paramMap.get('id') ?? '';
    this.isEdit = !!this.articleId;

    if (this.isEdit) {
      this.loading.set(true);
      this.kbSvc.getArticle(this.articleId).subscribe({
        next: art => {
          this.form = {
            tipo: art.tipo,
            titulo: art.titulo,
            contenido: art.contenido,
            categoria: art.categoria,
            tags: art.tags ?? [],
            activo: art.activo,
            orden: art.orden,
          };
          this.attachedDocs.set(art.documents ?? []);
          this.loading.set(false);
        },
        error: () => this.router.navigate(['/app/configuracion/knowledge-base']),
      });
    }

    // Cargar documentos disponibles siempre (creación y edición)
    this.loadingDocs.set(true);
    this.kbSvc.getDocuments({ per_page: 200 }).subscribe({
      next: data => {
        this.availableDocs.set(data.items);
        this.loadingDocs.set(false);
      },
      error: () => this.loadingDocs.set(false),
    });
  }

  attachDoc(doc: KbDocument): void {
    if (this.isEdit) {
      this.kbSvc.attachDocument(this.articleId, doc.id).subscribe(updatedArticle => {
        this.attachedDocs.set(updatedArticle.documents ?? []);
      });
    } else {
      this.attachedDocs.update(list => [...list, doc]);
    }
  }

  detachDoc(doc: KbDocument): void {
    if (this.isEdit) {
      this.kbSvc.detachDocument(this.articleId, doc.id).subscribe(() => {
        this.attachedDocs.update(list => list.filter(d => d.id !== doc.id));
      });
    } else {
      this.attachedDocs.update(list => list.filter(d => d.id !== doc.id));
    }
  }

  // ── Tags ─────────────────────────────────────────────────────────────────

  addTag(): void {
    const tag = this.tagInput.trim().toLowerCase();
    if (tag && !this.form.tags.includes(tag)) {
      this.form.tags = [...this.form.tags, tag];
    }
    this.tagInput = '';
  }

  removeTag(tag: string): void {
    this.form.tags = this.form.tags.filter(t => t !== tag);
  }

  onTagKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
      event.preventDefault();
      this.addTag();
    }
  }

  // ── Guardar ───────────────────────────────────────────────────────────────

  save(): void {
    if (!this.form.titulo || !this.form.contenido || !this.form.categoria) return;
    this.saving.set(true);

    if (this.isEdit) {
      this.kbSvc.updateArticle(this.articleId, this.form).subscribe({
        next: () => {
          this.saving.set(false);
          this.router.navigate(['/app/configuracion/knowledge-base']);
        },
        error: () => this.saving.set(false),
      });
    } else {
      this.kbSvc.createArticle(this.form).pipe(
        switchMap(newArticle => {
          const pending = this.attachedDocs();
          if (pending.length === 0) return of(null);
          return forkJoin(pending.map(doc => this.kbSvc.attachDocument(newArticle.id, doc.id)));
        })
      ).subscribe({
        next: () => {
          this.saving.set(false);
          this.router.navigate(['/app/configuracion/knowledge-base']);
        },
        error: () => this.saving.set(false),
      });
    }
  }

  cancel(): void {
    this.router.navigate(['/app/configuracion/knowledge-base']);
  }
}
