export declare function getTestdata(): {
    planID: string;
    ehemaligePlannamen: never[];
    planname: string;
    arbeitstitel: string;
    veroeffentlichungstitel: null;
    flaechenabgrenzung: {
        type: string;
        coordinates: number[][][][];
    };
    flaechenabgrenzungBbox: {
        type: string;
        coordinates: number[][];
    };
    flaechenabgrenzungWmsUrl: string;
    planstatus: {
        code: string;
        name: string;
    };
    verfahrenssteuerung: {
        code: string;
        name: string;
    };
    planart: {
        code: string;
        name: string;
    };
    flaeche: null;
    bezirk: never[];
    zustaendigkeit: {
        code: string;
        name: string;
    };
    verfahrensart: {
        code: string;
        name: string;
    };
    beschreibungGeltungsbereich: string;
    beschreibungPlanungsanlass: string;
    vorgeseheneAllgemeineNutzungen: never[];
    vorgeseheneBesondereNutzungen: never[];
    bodenordnungsgebiet: null;
    belegenheitBodenordnungsgebiet: never[];
    ausgleichsflaechen: never[];
    geplanteWohneinheiten: number;
    zusaetzlicheGewerbeflaechen: null;
    gefoerderterWohnungsbau: null;
    zurueckstellungen: never[];
    veraenderungssperren: never[];
    kontakte: never[];
    sachbearbeiterkennungen: string[];
    schlagwort: never[];
    codeVerfahrensschritt: {
        code: string;
        name: string;
    };
    verfahrensschritt: string;
    verfahrensteilschritt: null;
    unterverfahrensteilschritt: null;
    verfahrensstand: {
        code: string;
        name: string;
    };
    typ: string;
    verfahrensteilschritte: {
        codeVerfahrensschritt: {
            code: string;
            name: string;
        };
        codeVerfahrensteilschritt: {
            code: string;
            name: string;
        };
        vsDurchgangszaehler: number;
        durchgangszaehler: number;
        sitzungsergebnistyp: null;
        termine: ({
            terminID: string;
            beschreibung: string;
            klassifizierung: string;
            zeitplanungsrelevant: boolean;
            prognoserelevant: boolean;
            monitoringrelevant: boolean;
            verzoegert: boolean;
            modellverzoegerung: number;
            modelldauer: number;
            initialPrognostizierterZeitraum: null;
            geplanterOderBerechneterZeitraum: null;
            stattgefundenerZeitraum: null;
            datumstyp: {
                code: string;
                name: string;
            };
            datumsstatus: null;
            kommentar: null;
            sitzung: {
                sitzungscode: {
                    code: string;
                    name: string;
                };
                politischesErgebnis: null;
                verwaltungstechnischesErgebnis: null;
                rechtspruefungErgebnis: null;
                ergebnisBemerkung: null;
                versanddatum: null;
            };
            infoLandesplanerischeStellungnahme: null;
            infoAuftaktbeschluss: null;
            infoAufstellungsbeschluss: null;
            infoVeroeffentlichung: null;
            infoFruehzeitigeBeteiligungToeb: null;
            infoVerschickungToeb: null;
            infoOeffentlicheAuslegung: null;
            infoSenatssitzung: null;
            infoExterneAbstimmung: null;
            infoKfs: null;
            infoFoeb: null;
            infoToeb: null;
            infoStaatsarchiv: null;
            infoSchlussphase: null;
            infoFeststellung: null;
            infoFraktionssprecherbefassung: null;
            generischeEigenschaften: null;
            verzoegerungsgrund: null;
            codeVerfahrensschritt: {
                code: string;
                name: string;
            };
            codeVerfahrensteilschritt: {
                code: string;
                name: string;
            };
            durchgangszaehler: number;
            prognoseStarttermin: boolean;
        } | {
            terminID: string;
            beschreibung: string;
            klassifizierung: string;
            zeitplanungsrelevant: boolean;
            prognoserelevant: boolean;
            monitoringrelevant: boolean;
            verzoegert: boolean;
            modellverzoegerung: null;
            modelldauer: null;
            initialPrognostizierterZeitraum: null;
            geplanterOderBerechneterZeitraum: null;
            stattgefundenerZeitraum: null;
            datumstyp: {
                code: string;
                name: string;
            };
            datumsstatus: null;
            kommentar: null;
            sitzung: null;
            infoLandesplanerischeStellungnahme: null;
            infoAuftaktbeschluss: null;
            infoAufstellungsbeschluss: null;
            infoVeroeffentlichung: null;
            infoFruehzeitigeBeteiligungToeb: null;
            infoVerschickungToeb: null;
            infoOeffentlicheAuslegung: null;
            infoSenatssitzung: null;
            infoExterneAbstimmung: null;
            infoKfs: null;
            infoFoeb: null;
            infoToeb: null;
            infoStaatsarchiv: null;
            infoSchlussphase: null;
            infoFeststellung: null;
            infoFraktionssprecherbefassung: null;
            generischeEigenschaften: {
                type: string;
                name: string;
                value: null;
            }[];
            verzoegerungsgrund: null;
            codeVerfahrensschritt: {
                code: string;
                name: string;
            };
            codeVerfahrensteilschritt: {
                code: string;
                name: string;
            };
            durchgangszaehler: number;
            prognoseStarttermin: boolean;
        })[];
        stellungnahmenAnzGesamt: number;
        stellungnahmenAnzNeu: number;
        stellungnahmenAnzInBearbeitung: number;
        stellungnahmen: never[];
        erledigt: boolean;
    }[];
    vorweggenehmigungsreife: boolean;
    vorweggenehmigungsreifeSitzung: null;
    vorweggenehmigungsreifeAm: null;
    untergangsdatum: null;
    planwerkWmsUrl: string;
    parallelverfahrenPlanIDs: string[];
    besitzer: {
        nutzerID: string;
        vollerName: string;
        email: string;
    };
    mitwirkungsbereitschaft: null;
    beschlussdatum: {
        datumsstatus: {
            code: string;
            name: string;
        };
        beschlussdatum: string;
    };
    bekanntmachungsdatum: {
        datumsstatus: {
            code: string;
            name: string;
        };
        datum: string;
    };
    gebietseinheiten: {
        code: string;
        name: string;
    }[];
    prognostizierbar: boolean;
    hatZeitplanung: boolean;
    zeitpunktLetzteAenderung: number;
    relationPlanrecht: never[];
    laProVorgeseheneNutzungen: never[];
    vorgeseheneNutzungen: {
        code: string;
        name: string;
    }[];
    internetseite: null;
    gebaeudearten: never[];
    wettbewerbsarten: never[];
    teilnahmen: never[];
    rechtsgrundlage: null;
    belegenheitsort: null;
    baublock: null;
    projektgruppe: null;
    jurymitglieder: never[];
    ehemaligerPlanstatus: {
        code: string;
        name: string;
    };
    preisgerichtDatum: null;
    xplanMgrID: string;
    basisVerfahren: boolean;
};
