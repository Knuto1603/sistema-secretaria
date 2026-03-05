import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ProgramacionTablaComponent } from './programacion-tabla.component';

describe('ProgramacionTablaComponent', () => {
  let component: ProgramacionTablaComponent;
  let fixture: ComponentFixture<ProgramacionTablaComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProgramacionTablaComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ProgramacionTablaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
