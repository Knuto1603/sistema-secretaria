export interface ChatConversation {
  id: string;
  titulo: string;
  messages_count?: number;
  expires_at: string;
  created_at: string;
  updated_at: string;
}

export interface KbArticleRef {
  id: string;
  titulo: string;
  tipo: string;
}

export interface KbDocumentRef {
  id: string;
  titulo: string;
}

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant';
  contenido: string;
  context_articles: KbArticleRef[];
  context_documents: KbDocumentRef[];
  templates_sugeridos: KbDocumentRef[];
  created_at: string;
}

export interface ChatConversationDetail {
  conversation: {
    id: string;
    titulo: string;
    expires_at: string;
  };
  messages: ChatMessage[];
}

export interface ChatSendResponse {
  message: ChatMessage;
  sources: {
    articles: KbArticleRef[];
    documents: KbDocumentRef[];
    templates: KbDocumentRef[];
    related: KbArticleRef[];
  };
}

// Knowledge Base
export interface KbArticle {
  id: string;
  tipo: 'proceso' | 'faq' | 'norma' | 'requisito' | 'resolucion';
  titulo: string;
  contenido: string;
  categoria: string;
  tags: string[] | null;
  activo: boolean;
  orden: number;
  documents?: KbDocument[];
  relacionados?: KbArticle[];
}

export interface KbDocument {
  id: string;
  titulo: string;
  descripcion: string | null;
  filename: string;
  original_filename: string;
  mime_type: string;
  size_bytes: number;
  es_plantilla: boolean;
  procesado: boolean;
  activo: boolean;
  /** Artículos KB a los que está adjunto este documento (muchos a muchos) */
  knowledge_bases?: { id: string; titulo: string }[];
  created_at?: string;
}

// Analytics
export interface ChatAnalyticsSummary {
  periodo_dias: number;
  total_preguntas: number;
  respondidas_con_kb: number;
  sin_contexto_kb: number;
  tasa_cobertura_pct: number;
  tokens_usados: number;
}

export interface TopTopic {
  knowledge_base_id: string;
  titulo: string;
  tipo: string;
  categoria: string;
  consultas: number;
}

export interface KnowledgeGap {
  assistant_message_id: string;
  pregunta: string;
  fecha: string;
}

export interface PaginatedData<T> {
  items: T[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
